<?php

namespace Artax;

use Alert\Reactor,
    After\Failure,
    After\Success,
    After\Deferred;

class TcpPool implements SocketPool {
    const OP_HOST_CONNECTION_LIMIT = 'op.host-conn-limit';
    const OP_MAX_QUEUE_SIZE = 'op.max-queue-size';
    const OP_MS_IDLE_TIMEOUT = 'op.ms-idle-timeout';
    const OP_MS_CONNECT_TIMEOUT = TcpConnector::OP_MS_CONNECT_TIMEOUT;
    const OP_BIND_IP_ADDRESS = TcpConnector::OP_BIND_IP_ADDRESS;

    private $reactor;
    private $tcpConnector;
    private $sockets = [];
    private $queuedSocketRequests = [];
    private $socketIdUriMap = [];
    private $options = [
        self::OP_HOST_CONNECTION_LIMIT => 8,
        self::OP_MAX_QUEUE_SIZE => 512,
        self::OP_MS_IDLE_TIMEOUT => 10000,
        self::OP_MS_CONNECT_TIMEOUT => 30000,
        self::OP_BIND_IP_ADDRESS => '',
    ];
    private $opMaxConnectionsPerHost = 8;
    private $opMaxQueuedSockets = 512;
    private $opMsIdleTimeout = 10000;

    public function __construct(Reactor $reactor, TcpConnector $tcpConnector = null) {
        $this->reactor = $reactor;
        $this->tcpConnector = $tcpConnector ?: new TcpConnector($reactor);
    }

    /**
     * Checkout a socket from the specified hostname:port authority
     *
     * The resulting socket resource should be checked back in via SocketPool::checkin() once the
     * calling code is finished with the stream (even if the socket has been closed). Failure to
     * checkin sockets will result in memory leaks and socket queue blockage.
     *
     * @param string $name A string of the form somedomain.com:80 or 192.168.1.1:443
     * @return \After\Promise Returns a promise that resolves to a socket once a connection is available
     */
    public function checkout($uri, array $options = []) {
        $uri = strtolower($uri);
        $uriParts = @parse_url($uri);
        if (empty($uriParts['host']) || empty($uriParts['port'])) {
            return new Failure(new \DomainException(
                sprintf('URI requires both host and port components: %s', $uri)
            ));
        }
        $authority = $uriParts['host'] . ':' . $uriParts['port'];
        $options = $options ? array_merge($this->options, $options) : $this->options;

        return ($socket = $this->checkoutExistingSocket($uri, $options))
            ? new Success($socket)
            : $this->checkoutNewSocket($uri, $authority, $options);
    }

    private function checkoutExistingSocket($uri, $options) {
        if (empty($this->sockets[$uri])) {
            return null;
        }

        $needsRebind = false;

        foreach ($this->sockets[$uri] as $socketId => $tcpPoolStruct) {
            if (!$tcpPoolStruct->isAvailable) {
                continue;
            } elseif ($this->isSocketDead($tcpPoolStruct->resource)) {
                unset($this->sockets[$uri][$socketId]);
            } elseif (($bindToIp = @stream_context_get_options($tcpPoolStruct->resource)['socket']['bindto'])
                && ($bindToIp == $options[self::OP_BIND_IP_ADDRESS])
            ) {
                $tcpPoolStruct->isAvailable = false;
                $this->reactor->disable($tcpPoolStruct->idleWatcher);
                return $tcpPoolStruct->resource;
            } elseif ($bindToIp) {
                $needsRebind = true;
            } else {
                $tcpPoolStruct->isAvailable = false;
                $this->reactor->disable($tcpPoolStruct->idleWatcher);
                return $tcpPoolStruct->resource;
            }
        }

        $this->needsRebind = $needsRebind;

        return null;
    }

    private function checkoutNewSocket($uri, $authority, $options) {
        if ($this->allowsNewConnection($uri) || $this->needsRebind) {
            $deferred = new Deferred;
            $this->initializeNewConnection($deferred, $uri, $authority, $options);
            $this->needsRebind = false;
            return $deferred->promise();
        } elseif (count($this->queuedSocketRequests) < $this->opMaxQueuedSockets) {
            $deferred = new Deferred;
            $this->queuedSocketRequests[$uri][] = $deferred;
            return $deferred->promise();
        } else {
            return new Failure(new TooBusyException(
                'Request rejected: too busy. Try upping the OP_MAX_QUEUE_SIZE setting.'
            ));
        }
    }

    private function allowsNewConnection($uri) {
        if ($this->opMaxConnectionsPerHost <= 0) {
            return true;
        }
        if (empty($this->sockets[$uri])) {
            return true;
        }
        if (count($this->sockets[$uri]) < $this->opMaxConnectionsPerHost) {
            return true;
        }

        return false;
    }

    private function initializeNewConnection(Deferred $deferred, $uri, $authority, $options) {
        $deferredSocket = $this->tcpConnector->connect($authority, $options);
        $deferredSocket->onResolve(function($error, $socket) use ($deferred, $uri) {
            if ($error) {
                $deferred->fail($error);
            } else {
                $this->finalizeNewConnection($deferred, $uri, $socket);
            }
        });
    }

    private function finalizeNewConnection(Deferred $deferred, $uri, $socket) {
        $socketId = (int) $socket;
        $tcpPoolSockStruct = new TcpPoolStruct;
        $tcpPoolSockStruct->id = $socketId;
        $tcpPoolSockStruct->uri = $uri;
        $tcpPoolSockStruct->resource = $socket;
        $tcpPoolSockStruct->isAvailable = false;
        $this->sockets[$uri][$socketId] = $tcpPoolSockStruct;
        $this->socketIdUriMap[$socketId] = $uri;
        $deferred->succeed($tcpPoolSockStruct->resource);
    }

    /**
     * Remove the specified socket from the pool
     *
     * @param resource $resource
     * @return self
     */
    public function clear($resource) {
        $socketId = (int) $resource;
        if (isset($this->socketIdUriMap[$socketId])) {
            $uri = $this->socketIdUriMap[$socketId];
            $this->unloadSocket($uri, $socketId);
        }

        return $this;
    }

    private function unloadSocket($uri, $socketId) {
        $tcpPoolStruct = $this->sockets[$uri][$socketId];
        if ($tcpPoolStruct->idleWatcher) {
            $this->reactor->cancel($tcpPoolStruct->idleWatcher);
        }
        unset(
            $this->sockets[$uri][$socketId],
            $this->socketIdUriMap[$socketId]
        );
        if (!empty($this->queuedSocketRequests[$uri])) {
            $this->dequeueNextWaitingSocket($uri);
        }
    }

    private function dequeueNextWaitingSocket($uri) {
        $deferred = array_shift($this->queuedSocketRequests[$uri]);
        $this->initializeNewConnection($deferred, $uri);
        if (empty($this->queuedSocketRequests[$uri])) {
            unset($this->queuedSocketRequests[$uri]);
        }
    }

    /**
     * Return a previously checked-out socket to the pool
     *
     * @param resource $resource
     * @throws \DomainException on resource unknown to the pool
     * @return self
     */
    public function checkin($resource) {
        $socketId = (int) $resource;

        if (!isset($this->socketIdUriMap[$socketId])) {
            throw new \DomainException(
                sprintf('Unknown socket: %s', $resource)
            );
        }

        $uri = $this->socketIdUriMap[$socketId];

        if ($this->isSocketDead($resource)) {
            $this->unloadSocket($uri, $socketId);
        } else {
            $this->finalizeSocketCheckin($uri, $socketId);
        }

        return $this;
    }

    private function isSocketDead($resource) {
        return !is_resource($resource) || feof($resource);
    }

    private function finalizeSocketCheckin($uri, $socketId) {
        $tcpPoolStruct = $this->sockets[$uri][$socketId];
        $tcpPoolStruct->isAvailable = true;

        if (!empty($this->queuedSocketRequests[$uri])) {
            $this->dequeueNextWaitingSocket($uri);
        } elseif ($this->opMsIdleTimeout > 0) {
            $this->initializeIdleTimeout($tcpPoolStruct);
        }
    }

    private function initializeIdleTimeout(TcpPoolStruct $tcpPoolStruct) {
        if ($tcpPoolStruct->idleWatcher === null) {
            $tcpPoolStruct->idleWatcher = $this->reactor->once(function() use ($tcpPoolStruct) {
                $uri = $tcpPoolStruct->uri;
                $socketId = $tcpPoolStruct->id;
                $this->unloadSocket($uri, $socketId);
            }, $this->opMsIdleTimeout);
        } else {
            $this->reactor->enable($tcpPoolStruct->idleWatcher);
        }
    }

    /**
     * Set socket pool options
     *
     * @param int $option
     * @param mixed $value
     * @return self
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_HOST_CONNECTION_LIMIT:
                $this->options[self::OP_HOST_CONNECTION_LIMIT] = (int) $value;
                break;
            case self::OP_MAX_QUEUE_SIZE:
                $this->options[self::OP_MAX_QUEUE_SIZE] = (int) $value;
                break;
            case self::OP_MS_CONNECT_TIMEOUT:
                $this->options[self::OP_MS_CONNECT_TIMEOUT] = $value;
                break;
            case self::OP_MS_IDLE_TIMEOUT:
                $this->options[self::OP_MS_IDLE_TIMEOUT] = (int) $value;
                break;
            case self::OP_BIND_IP_ADDRESS:
                $this->options[self::OP_BIND_IP_ADDRESS] = $value;
                break;
            default:
                throw new \DomainException(
                    sprintf('Unknown option: %s', $option)
                );
        }

        return $this;
    }
}
