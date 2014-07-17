<?php

namespace Artax;

use Alert\Reactor,
    After\Deferred,
    After\Failure,
    Addr\ResolverFactory;

class SocketPool {
    const OP_HOST_CONNECTION_LIMIT = 0;
    const OP_QUEUED_SOCKET_LIMIT = 1;
    const OP_MS_CONNECT_TIMEOUT = 2;
    const OP_MS_IDLE_TIMEOUT = 3;
    const OP_MS_DNS_TIMEOUT = 4;
    const OP_BIND_IP_ADDRESS = 5;

    /**
     * Address family constants
     */
    const ADDR_INET4 = 1;
    const ADDR_INET6 = 2;

    private $reactor;
    private $sockets = [];
    private $queuedSockets = [];
    private $socketIdNameMap = [];
    private $opMaxConnectionsPerHost = 8;
    private $opMaxQueuedSockets = 512;
    private $opMsConnectTimeout = 30000;
    private $opMsIdleTimeout = 10000;
    private $opMsDnsTimeout = 50000;
    private $opBindIpAddress = null;
    private $tlsSettings = [
        'verify_peer' => null,
        'allow_self_signed' => null,
        'cafile' => null,
        'capath' => null,
        'local_cert' => null,
        'passphrase' => null,
        'CN_match' => null,
        'verify_depth' => null,
        'ciphers' => null,
        'SNI_enabled' => null,
        'SNI_server_name' => null
    ];

    public function __construct(Reactor $reactor, Resolver $dnsResolver = null) {
        $this->reactor = $reactor;
        $this->dnsResolver = $dnsResolver ?: (new ResolverFactory)->createResolver($reactor);
        $this->populateDefaultTlsSettings();
    }

    private function populateDefaultTlsSettings() {
        // @TODO Assign $this->tlsSettings according to the current PHP version
        // @TODO Decide if PHP < 5.6 should even be allowed for encrypted connections. Anything
        //       older is patently unsafe ...
    }

    /**
     * Set socket pool options
     *
     * @param int $option
     * @param mixed $value
     * @return void
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_HOST_CONNECTION_LIMIT:
                $this->opMaxConnectionsPerHost = (int) $value;
                break;
            case self::OP_QUEUED_SOCKET_LIMIT:
                $this->opMaxQueuedSockets = (int) $value;
                break;
            case self::OP_MS_CONNECT_TIMEOUT:
                $this->opMsConnectTimeout = (int) $value;
                break;
            case self::OP_MS_IDLE_TIMEOUT:
                $this->opMsIdleTimeout = (int) $value;
                break;
            case self::OP_MS_DNS_TIMEOUT:
                $this->opMsDnsTimeout = (int) $value;
                break;
            case self::OP_BIND_IP_ADDRESS:
                $this->opBindIpAddress = $value;
                break;
            default:
                throw new \DomainException(
                    sprintf('Unknown option: %s', $option)
                );
        }
    }

    /**
     * Checkout a socket from the specified hostname:port authority
     *
     * The resulting socket resource should be checked back in via SocketPool::checkin() once the
     * calling code is finished with the stream (even if the socket has been closed). Failure to
     * checkin sockets will result in memory leaks and socket queue blockage.
     *
     * @param string $name A string of the form somedomain.com:80 or 192.168.1.1:443
     * @return After\Promise Returns a promise that resolves to a socket once a connection is available
     */
    public function checkout($name) {
        $urlParts = @parse_url($name);
        $host = $urlParts['host'];
        $port = $urlParts['port']; // @TODO Error if missing port

        $dnsStruct = new DnsStruct;
        $dnsStruct->name = $name;
        $dnsStruct->host = $host;
        $dnsStruct->port = $port;
        $dnsStruct->deferredIp = new Deferred;

        $this->dnsResolver->resolve($host, function($resolvedIp, $ipType) use ($dnsStruct) {
            $this->onDnsResolution($dnsStruct, $resolvedIp, $ipType);
        });

        return $dnsStruct->deferredIp->promise();
    }

    private function onDnsResolution(DnsStruct $dnsStruct, $resolvedIp, $ipType) {
        $deferredIp = $dnsStruct->deferredIp;

        if ($resolvedIp !== null) {
            $name = $dnsStruct->name;
            $port = $dnsStruct->port;
            $addr = ($ipType === self::ADDR_INET6)
                ? "[{$resolvedIp}]:{$port}"
                : "{$resolvedIp}:{$port}";
            $socket = $this->checkoutExistingSocket($name) ?: $this->checkoutNewSocket($name, $addr);
            $deferredIp->succeed($socket);
        } else {
            $deferredIp->fail(new DnsException(
                sprintf('DNS resolution failed for %s (error code: %d)', $dnsStruct->host, $ipType)
            ));
        }
    }

    private function checkoutExistingSocket($name) {
        if (empty($this->sockets[$name])) {
            return null;
        }
        foreach ($this->sockets[$name] as $socketId => $socketStruct) {
            if ($socketStruct->state !== SocketStruct::CHECKED_IN) {
                continue;
            } elseif ($this->isSocketDead($socketStruct->resource)) {
                unset($this->sockets[$name][$socketId]);
            } else {
                $socketStruct->state = SocketStruct::CHECKED_OUT;
                $this->reactor->disable($socketStruct->idleTimeoutWatcher);
                return $socketStruct->resource;
            }
        }
        return null;
    }

    private function checkoutNewSocket($name, $addr) {
        if ($this->allowsNewConnection($name)) {
            return $this->initializeNewConnection($name, $addr);
        } elseif (count($this->queuedSockets) < $this->opMaxQueuedSockets) {
            $deferred = new Deferred;
            $this->queuedSockets[$name][] = [$addr, $deferred];
            return $deferred->promise();
        } else {
            return new Failure(new TooBusyException(
                'Request rejected: too busy'
            ));
        }
    }

    private function allowsNewConnection($name) {
        if ($this->opMaxConnectionsPerHost <= 0) {
            return true;
        }
        if (empty($this->sockets[$name])) {
            return true;
        }
        if (count($this->sockets[$name]) < $this->opMaxConnectionsPerHost) {
            return true;
        }

        return false;
    }

    private function initializeNewConnection($name, $addr) {
        $deferredSocket = new Deferred;
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $timeout = 42; // <--- timeout not applicable when STREAM_CLIENT_ASYNC_CONNECT is used
        $ctx = $this->generateStreamSocketContext();
        $socket = @stream_socket_client($addr, $errno, $errstr, $timeout, $flags, $ctx);

        if ($socket) {
            $socketId = (int) $socket;
            $socketStruct = new SocketStruct;
            $socketStruct->id = $socketId;
            $socketStruct->name = $name;
            $socketStruct->resource = $socket;
            $socketStruct->deferredSocket = $deferredSocket;
            $this->sockets[$name][$socketId] = $socketStruct;
            $this->socketIdNameMap[$socketId] = $name;
            $this->initializePendingSocketStruct($socketStruct);
        } else {
            $socketStruct->state = SocketStruct::ERROR;
            $deferredSocket->fail(new SocketException(
                sprintf('Connection to %s failed: [Error #%d] %s', $authority, $errno, $errstr)
            ));
        }

        return $deferredSocket->promise();
    }

    private function generateStreamSocketContext() {
        $opts = [];

        if ($this->opBindIpAddress) {
            $opts['socket']['bindto'] = $this->opBindIpAddress;
        }

        // @TODO Add SSL/TLS context stuff here

        return stream_context_create($opts);
    }

    private function initializePendingSocketStruct(SocketStruct $socketStruct) {
        $resource = $socketStruct->resource;
        stream_set_blocking($resource, false);

        $socketStruct->state = SocketStruct::CONNECTING;
        $socketStruct->ioWriteWatcher = $this->reactor->onWritable($resource, function() use ($socketStruct) {
            $this->initializeConnectedSocketStruct($socketStruct);
        });

        if ($this->opMsConnectTimeout > 0) {
            $socketStruct->connectTimeoutWatcher = $this->reactor->once(function() use ($socketStruct) {
                $this->onConnectTimeout($socketStruct);
            }, $this->opMsConnectTimeout);
        }
    }

    private function onConnectTimeout(SocketStruct $socketStruct) {
        unset($this->sockets[$socketStruct->name][$socketStruct->id]);
        $this->reactor->cancel($socketStruct->ioWriteWatcher);
        $this->reactor->cancel($socketStruct->connectTimeoutWatcher);
        $socketStruct->state = SocketStruct::ERROR;
        $socketStruct->deferredSocket->fail(new SocketException(
            sprintf('Socket connect timeout exceeded: %d ms', $this->opMsConnectTimeout)
        ));
    }

    private function initializeConnectedSocketStruct(SocketStruct $socketStruct) {
        if (isset(stream_context_get_options($socketStruct->resource)['ssl'])) {
            // We create a new watcher for TLS enabling so cancel the old one now. It's better
            // to keep this cancellation here because the encryption method may be called multiple
            // times and it would be wasteful to create a new watcher each time the socket is
            // writable when enabling crypto.
            $this->reactor->cancel($socketStruct->ioWriteWatcher);
            $this->encryptConnectedSocketStruct($socketStruct);
        } else {
            $this->finalizeConnectedSocketStruct($socketStruct);
        }
    }

    private function encryptConnectedSocketStruct(SocketStruct $socketStruct) {
        $socketStruct->state = SocketStruct::ENCRYPTING;
        // @TODO Allow method assignment from stream context.
        $tlsMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        $resource = $socketStruct->resource;
        $crypto = @stream_socket_enable_crypto($resource, true, $tlsMethod);

        if ($crypto) {
            $this->finalizeConnectedSocketStruct($socketStruct);
        } elseif ($result === false) {
            $errMsg = error_get_last()['message'];
            $e = new SocketException($errMsg, self::E_TLS_HANDSHAKE_FAILED);
            $this->notifyObservations(self::ERROR, $e);
            $this->stop();
        } elseif ($socketStruct->ioWriteWatcher === null) {
            $socketStruct->ioWriteWatcher = $this->reactor->onWritable($resource, function() use ($socketStruct) {
                $this->encryptConnectedSocketStruct($socketStruct);
            });
        }
    }

    private function finalizeConnectedSocketStruct(SocketStruct $socketStruct) {
        $socketStruct->state = SocketStruct::CHECKED_OUT;
        $this->reactor->cancel($socketStruct->ioWriteWatcher);
        $this->reactor->cancel($socketStruct->connectTimeoutWatcher);
        $socketStruct->deferredSocket->succeed($socketStruct->resource);
        $socketStruct->deferredSocket = null;
    }

    /**
     * Remove the specified socket from the pool
     *
     * @param resource $resource
     * @return void
     */
    public function clear($resource) {
        $socketId = (int) $resource;
        if (isset($this->socketIdNameMap[$socketId])) {
            $name = $this->socketIdNameMap[$socketId];
            $this->unloadSocket($name, $socketId);
        }
    }

    /**
     * Return a previously checked-out socket to the pool
     *
     * @param resource $resource
     * @throws \DomainException on resource unknown to the pool
     * @return void
     */
    public function checkin($resource) {
        $socketId = (int) $resource;

        if (!isset($this->socketIdNameMap[$socketId])) {
            throw new \DomainException(
                sprintf('Unknown socket: %s', $resource)
            );
        }

        $name = $this->socketIdNameMap[$socketId];
        $socketStruct = $this->sockets[$name][$socketId];
        $socketStruct->state = SocketStruct::CHECKED_IN;

        if ($this->isSocketDead($resource)) {
            $this->unloadSocket($name, $socketId);
        } else {
            $this->finalizeLiveSocketCheckin($socketStruct);
        }
    }

    private function isSocketDead($resource) {
        return !is_resource($resource) || feof($resource);
    }

    private function unloadSocket($name, $socketId) {
        $socketStruct = $this->sockets[$name][$socketId];
        $this->reactor->cancel($socketStruct->ioWriteWatcher);
        $this->reactor->cancel($socketStruct->connectTimeoutWatcher);
        $this->reactor->cancel($socketStruct->idleTimeoutWatcher);
        unset(
            $this->sockets[$name][$socketId],
            $this->socketIdNameMap[$socketId]
        );
    }

    private function finalizeLiveSocketCheckin(SocketStruct $socketStruct) {
        if (!empty($this->queuedSockets[$socketStruct->name])) {
            $this->dequeueNextSocketRequest($socketStruct);
        } elseif ($this->opMsIdleTimeout > 0) {
            $this->initializeIdleTimeout($socketStruct);
        }
    }

    private function initializeIdleTimeout(SocketStruct $socketStruct) {
        if ($socketStruct->idleTimeoutWatcher === null) {
            $socketStruct->idleTimeoutWatcher = $this->reactor->once(function() use ($socketStruct) {
                $this->onIdleTimeout($socketStruct);
            }, $this->opMsIdleTimeout);
        } else {
            $this->reactor->enable($socketStruct->idleTimeoutWatcher);
        }
    }

    private function onIdleTimeout(SocketStruct $socketStruct) {
        @fclose($socketStruct->resource);
        $this->unloadSocket($socketStruct->name, $socketStruct->id);
    }

    private function dequeueNextSocketRequest(SocketStruct $socketStruct) {
        $name = $socketStruct->name;
        list($addr, $deferredSocket) = array_shift($this->queuedSockets[$name]);
        if (empty($this->queuedSockets[$name])) {
            unset($this->queuedSockets[$name]);
        }

        $socketStruct->state = SocketStruct::CHECKED_OUT;
        $deferredSocket->succeed($socketStruct->resource);
    }
}