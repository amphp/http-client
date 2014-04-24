<?php

namespace Artax;

use Alert\Reactor;

class Socket implements Observable {
    
    use ObservableSubject;
    
    const S_UNCONNECTED = 0;
    const S_PENDING = 1;
    const S_CONNECTED = 2;
    
    const E_CONNECT_TIMEOUT = 900;
    const E_CONNECT_FAILURE = 901;
    const E_SOCKET_GONE = 902;
    const E_TLS_HANDSHAKE_FAILED = 903;
    const E_WOULDBLOCK = 10035;
    
    const READY = 'ready';
    const SEND = 'send';
    const DATA = 'data';
    const DRAIN = 'drain';
    const ERROR = 'error';
    const CONNECT = 'connect';
    
    private $reactor;
    private $state = self::S_UNCONNECTED;
    private $socket;
    private $authority;
    private $readWatcher;
    private $writeWatcher;
    private $writeBuffer;
    private $connectedAt;
    private $lastDataRcvdAt;
    
    private $connectTimeout = 5;
    private $ioGranularity = 65536;
    private $tlsOptions;
    private $isTls = FALSE;
    private $bindToIp;
    
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
        if ($this->state) {
            $this->notifyObservations(self::READY);
        } else {
            $this->doConnect();
        }
    }
    
    function stop() {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        
        $this->reactor->cancel($this->writeWatcher);
        $this->reactor->cancel($this->readWatcher);
    }
    
    private function doConnect() {
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $timeout = 42; // <--- not applicable with STREAM_CLIENT_ASYNC_CONNECT
        $ctx = $this->generateContext();
        $socket = @stream_socket_client($this->authority, $errNo, $errStr, $timeout, $flags, $ctx);
        
        if ($socket || $errNo === self::E_WOULDBLOCK) {
            $this->initializePendingSock($socket);
        } else {
            $this->state = self::S_UNCONNECTED;
            $error = new SocketException(NULL, self::E_CONNECT_FAILURE);
            $this->notifyObservations(self::ERROR, $error);
        }
    }
    
    private function generateContext() {
        $opts = [];
        
        if ($this->bindToIp) {
            $opts['socket']['bindto'] = $this->bindToIp;
        }
        if ($this->isTls) {
            $opts['ssl'] = array_filter($this->tlsOptions, function($k) { return !is_null($k); });
        }
        
        return stream_context_create($opts);
    }
    
    private function initializePendingSock($socket) {
        stream_set_blocking($socket, FALSE);
        
        $this->socket = $socket;
        $this->state = self::S_PENDING;
        
        $this->writeWatcher = $this->reactor->onWritable($this->socket, function() {
            $this->initializeConnectedSock();
        });
        
        if ($this->connectTimeout >= 0) {
            $this->timeoutWatcher = $this->reactor->once(function() {
                $this->state = self::S_UNCONNECTED;
                $msg = sprintf(
                    "Attempt to connect lasted more than %d seconds",
                    $this->connectTimeout
                );
                $error = new SocketException($msg, self::E_CONNECT_TIMEOUT);
                $this->notifyObservations(self::ERROR, $error);
                $this->stop();
            }, $this->connectTimeout);
        }
    }
    
    private function initializeConnectedSock() {
        if ($this->isTls) {
            $this->reactor->cancel($this->writeWatcher);
            $this->writeWatcher = $this->reactor->onWritable($this->socket, function() {
                $this->enableSockEncryption();
            });
        } else {
            $this->finalizeConnection();
        }
    }
    
    private function enableSockEncryption() {
        $result = @stream_socket_enable_crypto($this->socket, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        if ($result) {
            $this->finalizeConnection();
        } elseif ($result === FALSE) {
            $errMsg = error_get_last()['message'];
            $e = new SocketException($errMsg, self::E_TLS_HANDSHAKE_FAILED);
            $this->notifyObservations(self::ERROR, $e);
            $this->stop();
        }
    }
    
    private function finalizeConnection() {
        $this->state = self::S_CONNECTED;
        
        $this->readWatcher = $this->reactor->onReadable($this->socket, function() {
            $this->read();
        });
        
        $this->reactor->cancel($this->writeWatcher);
        $this->writeWatcher = $this->reactor->onWritable($this->socket, function() {
            $this->doSend();
        }, $enableNow = FALSE);
        
        $this->connectedAt = microtime(TRUE);
        $this->notifyObservations(self::CONNECT);
        $this->notifyObservations(self::READY);
    }
    
    private function read() {
        while (($data = @fread($this->socket, $this->ioGranularity)) || $data === '0') {
            $this->lastDataRcvdAt = microtime(TRUE);
            $this->notifyObservations(self::DATA, $data);
        }
        
        if (!is_resource($this->socket) || @feof($this->socket)) {
            $this->state = self::S_UNCONNECTED;
            $error = new SocketException(NULL, self::E_SOCKET_GONE);
            $this->notifyObservations(self::ERROR, $error);
        }
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
            $this->notifyObservations(self::SEND, $this->writeBuffer);
            $this->writeBuffer = '';
            $this->reactor->disable($this->writeWatcher);
            $this->notifyObservations(self::DRAIN);
        } elseif ($bytesSent !== FALSE) {
            $this->notifyObservations(self::SEND, substr($this->writeBuffer, 0, $bytesSent));
            $this->writeBuffer = substr($this->writeBuffer, $bytesSent);
            $this->reactor->enable($this->writeWatcher);
        } elseif (!is_resource($this->socket) || @feof($this->socket)){
            $this->state = self::S_UNCONNECTED;
            $error = new SocketException(NULL, self::E_SOCKET_GONE);
            $this->notifyObservations(self::ERROR, $error);
        }
    }
    
    /**
     * Set multiple socket options at once
     * 
     * @param array $options An array matching option keys to their values
     * @throws \DomainException On unknown option key
     * @return void
     */
    function setAllOptions(array $options) {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }
    
    /**
     * Set a socket option
     * 
     * @param string $key An option key
     * @param mixed $value The option value to assign
     * @throws \DomainException On unknown option key
     * @return void
     */
    function setOption($key, $value) {
        if ($this->state) {
            throw new \LogicException(
                'Socket options may not be modified after connection is initialized'
            );
        }
        
        switch (strtolower($key)) {
            case 'connecttimeout':
                $this->setConnectTimeout($value);
                break;
            case 'bindtoip':
                $this->setBindToIp($value);
                break;
            case 'iogranularity':
                $this->setIoGranularity($value);
                break;
            case 'tlsoptions':
                $this->setTlsOptions($value);
                break;
            default:
                throw new \DomainException(
                    "Unknown option key: {$key}"
                );
        }
    }
    
    private function setConnectTimeout($seconds) {
        $this->connectTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 5,
            'min_range' => -1
        )));
    }
    
    private function setBindToIp($ip) {
        $this->bindToIp = filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    private function setIoGranularity($bytes) {
        $this->ioGranularity = filter_var($bytes, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 65536,
            'min_range' => 1
        )));
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
        $this->removeAllObservations();
    }
}

