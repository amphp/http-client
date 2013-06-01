<?php

namespace Artax;

use Amp\Reactor;

class Socket implements Observable {
    
    use Subject;
    
    const S_UNCONNECTED = 0;
    const S_PENDING = 1;
    const S_CONNECTED = 2;
    const S_PAUSED = 3;
    
    const E_CONNECT_TIMEOUT = 900;
    const E_CONNECT_FAILURE = 901;
    const E_SOCKET_GONE = 902;
    const E_TLS_HANDSHAKE_FAILED = 903;
    const E_WOULDBLOCK = 10035;
    
    const CONNECT = 'connect';
    const TIMEOUT = 'timeout';
    
    private $reactor;
    private $state = self::S_UNCONNECTED;
    private $socket;
    private $authority;
    private $onReadable;
    private $onWritable;
    
    private $connectTimeout = 5;
    private $keepAliveTimeout = 30;
    private $bindToIp;
    private $ioGranularity = 65536;
    private $tlsHandshakeTimeout = 5;
    private $tlsOptions;
    private $isTls = FALSE;
    private $tlsHandshakeStartedAt;
    
    private $connectedAt;
    private $lastDataRcvdAt;
    
    function __construct(Reactor $reactor, $authority) {
        $this->reactor = $reactor;
        $this->authority = $authority;
        $this->tlsOptions = array(
            'verify_peer' => NULL,
            'allow_self_signed' => NULL,
            'cafile' => NULL,
            'capath' => NULL,
            'local_cert' => NULL,
            'passphrase' => NULL,
            'CN_match' => NULL,
            'verify_depth' => NULL,
            'ciphers' => NULL,
            'SNI_enabled' => NULL,
            'SNI_server_name' => NULL
        );
    }
    
    function start() {
        if (!$this->state) {
            $this->doConnect();
        } elseif ($this->state === self::S_PAUSED) {
            $this->enableIoSubscriptions();
            $this->state = self::S_CONNECTED;
            $this->notify(self::READY);
        } else {
            $this->notify(self::READY);
        }
    }
    
    function pause() {
        if ($this->state === self::S_CONNECTED) {
            $this->disableIoSubscriptions();
            $this->state = self::S_PAUSED;
        }
    }
    
    function stop() {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        
        $this->cancelSubscriptions();
    }
    
    private function enableIoSubscriptions() {
        $this->onWritable->enable();
        $this->onReadable->enable();
    }
    
    private function disableIoSubscriptions() {
        $this->onWritable->disable();
        $this->onReadable->disable();
    }
    
    private function cancelSubscriptions() {
        if ($this->onWritable) {
            $this->onWritable->cancel();
            $this->onWritable = NULL;
        }
        
        if ($this->onReadable) {
            $this->onReadable->cancel();
            $this->onReadable = NULL;
        }
    }
    
    private function doConnect() {
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $timeout = 42; // <--- not applicable with STREAM_CLIENT_ASYNC_CONNECT
        $ctx = $this->generateContext();
        $this->socket = @stream_socket_client($this->authority, $errNo, $errStr, $timeout, $flags, $ctx);
        
        if ($this->socket || $errNo === self::E_WOULDBLOCK) {
            $this->state = self::S_PENDING;
            $connectTimeout = ($this->connectTimeout >= 0) ? $this->connectTimeout : -1;
            $this->onWritable = $this->reactor->onWritable($this->socket, function($s, $trigger) {
                $this->initializeConnectedSock($trigger);
            }, $connectTimeout);
        } else {
            $this->state = self::S_UNCONNECTED;
            $error = new SocketException(NULL, self::E_CONNECT_FAILURE);
            $this->notify(self::ERROR, $error);
        }
    }
    
    private function generateContext() {
        $opts = array();
        if ($this->bindToIp) {
            $opts['socket']['bindto'] = $this->bindToIp;
        }
        if ($this->isTls) {
            $opts['ssl'] = array_filter($this->tlsOptions, function($k) { return !is_null($k); });
        }
        
        return stream_context_create($opts);
    }
    
    private function initializeConnectedSock($trigger) {
        if ($trigger === Reactor::TIMEOUT) {
            return $this->doConnectTimeout();
        }
        
        stream_set_blocking($this->socket, FALSE);
        
        $this->onWritable->cancel();
        $this->onWritable = NULL;
        
        if ($this->isTls) {
            $this->initializeTlsSubscription();
        } else {
            $this->finalizeConnection();
        }
    }
    
    private function doConnectTimeout() {
        $error = new SocketException(NULL, self::E_CONNECT_TIMEOUT);
        $this->notify(self::ERROR, $error);
        $this->stop();
    }
    
    private function initializeTlsSubscription() {
        $this->tlsHandshakeStartedAt = time();
        $this->onWritable = $this->reactor->onWritable($this->socket, function() {
            $this->enableTls();
        });
    }
    
    private function enableTls() {
        $result = @stream_socket_enable_crypto($this->socket, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        if ($result) {
            $this->onWritable->cancel();
            $this->onWritable = NULL;
            $this->finalizeConnection();
        } elseif ($result === FALSE) {
            $errMsg = error_get_last()['message'];
            $e = new SocketException($errMsg, self::E_TLS_HANDSHAKE_FAILED);
            $this->notify(self::ERROR, $e);
            $this->stop();
        } elseif (time() > ($this->tlsHandshakeStartedAt + $this->tlsHandshakeTimeout)) {
            $this->doConnectTimeout();
        }
    }
    
    private function finalizeConnection() {
        $this->state = self::S_CONNECTED;
        $this->onReadable = $this->reactor->onReadable(
            $this->socket,
            function($sock, $trigger) { $this->read($trigger); },
            $this->keepAliveTimeout
        );
        $this->connectedAt = microtime(TRUE);
        $this->notify(self::CONNECT);
        $this->notify(self::READY);
    }
    
    private function read($trigger) {
        if ($trigger === Reactor::TIMEOUT) {
            $this->notify(self::TIMEOUT);
        } else {
            $this->lastDataRcvdAt = microtime(TRUE);
            $this->doRead();
        }
    }
    
    private function doRead() {
        while (TRUE) {
            $data = @fread($this->socket, $this->ioGranularity);
            
            if (strlen($data)) {
                $this->notify(self::DATA, $data);
            } else {
                break;
            }
        }
        
        if (!$this->isConnected()) {
            $this->state = self::S_UNCONNECTED;
            $error = new SocketException(NULL, self::E_SOCKET_GONE);
            $this->notify(self::ERROR, $error);
        }
    }
    
    function isConnected() {
        return ($this->state === self::S_CONNECTED && is_resource($this->socket) && !feof($this->socket));
    }
    
    function send($data) {
        $this->writeBuffer .= $data;
        
        switch ($this->state) {
            case self::S_UNCONNECTED:
                $this->start();
                break;
            case self::S_PENDING:
                break;
            case self::S_CONNECTED:
                $this->doSend();
                break;
        }
    }
    
    private function doSend() {
        $dataLen = strlen($this->writeBuffer);
        $bytesSent = @fwrite($this->socket, $this->writeBuffer);
        
        if ($bytesSent === $dataLen) {
            $this->notify(self::SEND, $this->writeBuffer);
            $this->writeBuffer = '';
            $this->disableWriteSubscription();
            $this->notify(self::DRAIN);
        } elseif ($bytesSent > 0) {
            $this->notify(self::SEND, substr($this->writeBuffer, 0, $bytesSent));
            $this->writeBuffer = substr($this->writeBuffer, $bytesSent);
            $this->enableWriteSubscription();
        } elseif ($bytesSent === FALSE && !(is_resource($this->socket) && !feof($this->socket))){
            $this->state = self::S_UNCONNECTED;
            $error = new SocketException(NULL, self::E_SOCKET_GONE);
            $this->notify(self::ERROR, $error);
        }
    }
    
    private function disableWriteSubscription() {
        if ($this->onWritable) {
            $this->onWritable->disable();
        }
    }
    
    private function enableWriteSubscription() {
        if (!$this->onWritable) {
            $this->onWritable = $this->reactor->onReadable($this->socket, function() {
                $this->doSend();
            });
        } else {
            $this->onWritable->enable();
        }
    }
    
    function setAllOptions(array $options) {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }
    
    function setOption($key, $value) {
        if ($this->state) {
            throw new \LogicException(
                'Socket options may not be modified after connection is initialized'
            );
        }
        
        $validKeys = array(
            'connectTimeout',
            'keepAliveTimeout',
            'bindToIp',
            'ioGranularity',
            'tlsHandshakeTimeout',
            'tlsOptions'
        );
        
        if (in_array($key, $validKeys)) {
            $setter = 'set' . ucfirst($key);
            $this->$setter($value);
        } else {
            throw new \DomainException(
                'Invalid option key: ' . $key
            );
        }
    }
    
    private function setConnectTimeout($seconds) {
        $this->connectTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 5,
            'min_range' => -1
        )));
    }
    
    private function setKeepAliveTimeout($seconds) {
        $this->keepAliveTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 30,
            'min_range' => -1
        )));
    }
    
    private function setTlsHandshakeTimeout($seconds) {
        $this->tlsHandshakeTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 5,
            'min_range' => -1
        )));
    }
    
    private function setIoGranularity($bytes) {
        $this->ioGranularity = filter_var($bytes, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 65536,
            'min_range' => 1
        )));
    }
    
    private function setBindToIp($ip) {
        $this->bindToIp = filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    private function setTlsOptions(array $opt) {
        $opt = array_filter(array_intersect_key($opt, $this->tlsOptions), function($k) { return !is_null($k); });
        $this->tlsOptions = array_merge($this->tlsOptions, $opt);
        $this->isTls = TRUE;
    }
    
    function getAuthority() {
        return $this->authority;
    }
    
    function getConnectedAt() {
        return $this->connectedAt;
    }
    
    function getLastDataRcvdAt() {
        return $this->lastDataRcvdAt;
    }
    
    function __destruct() {
        $this->stop();
        $this->unsubscribeAll();
    }
}

