<?php

namespace Artax;

use Alert\Reactor,
    After\Failure,
    After\Success,
    After\Future,
    Acesync\Connector;

class SocketPool {
    const OP_HOST_CONNECTION_LIMIT = 'op.host-conn-limit';
    const OP_MAX_QUEUE_SIZE = 'op.max-queue-size';
    const OP_MS_IDLE_TIMEOUT = 'op.ms-idle-timeout';
    const OP_MS_CONNECT_TIMEOUT = Connector::OP_MS_CONNECT_TIMEOUT;
    const OP_BINDTO = Connector::OP_BIND_IP_ADDRESS;

    private $reactor;
    private $connector;
    private $sockets = [];
    private $queuedSocketRequests = [];
    private $socketIdUriMap = [];
    private $options = [
        self::OP_HOST_CONNECTION_LIMIT => 8,
        self::OP_MAX_QUEUE_SIZE => 512,
        self::OP_MS_IDLE_TIMEOUT => 10000,
        self::OP_MS_CONNECT_TIMEOUT => 30000,
        self::OP_BINDTO => '',
    ];
    private $opMaxConnectionsPerHost = 8;
    private $opMaxQueuedSockets = 512;
    private $opMsIdleTimeout = 10000;
    private $needsRebind;

    public function __construct(Reactor $reactor, Connector $connector = null) {
        $this->reactor = $reactor;
        $this->connector = $connector ?: new Connector($reactor);
    }

    /**
     * Checkout a socket from the specified URI authority
     *
     * The resulting socket resource should be checked back in via SocketPool::checkin() once the
     * calling code is finished with the stream (even if the socket has been closed). Failure to
     * checkin sockets will result in memory leaks and socket queue blockage.
     *
     * @param string $uri A string of the form somedomain.com:80 or 192.168.1.1:443
     * @param array $options
     * @return \After\Promise Returns a promise that resolves to a socket once a connection is available
     */
    public function checkout($uri, array $options = []) {
        $uri = (stripos($uri, 'unix://') === 0) ? $uri : strtolower($uri);
        $options = $options ? array_merge($this->options, $options) : $this->options;

        return ($socket = $this->checkoutExistingSocket($uri, $options))
            ? new Success($socket)
            : $this->checkoutNewSocket($uri, $options);
    }

    private function checkoutExistingSocket($uri, $options) {
        if (empty($this->sockets[$uri])) {
            return null;
        }

        $needsRebind = false;

        foreach ($this->sockets[$uri] as $socketId => $poolStruct) {
            if (!$poolStruct->isAvailable) {
                continue;
            } elseif ($this->isSocketDead($poolStruct->resource)) {
                unset($this->sockets[$uri][$socketId]);
            } elseif (($bindToIp = @stream_context_get_options($poolStruct->resource)['socket']['bindto'])
                && ($bindToIp == $options[self::OP_BINDTO])
            ) {
                $poolStruct->isAvailable = false;
                $this->reactor->disable($poolStruct->idleWatcher);
                return $poolStruct->resource;
            } elseif ($bindToIp) {
                $needsRebind = true;
            } else {
                $poolStruct->isAvailable = false;
                $this->reactor->disable($poolStruct->idleWatcher);
                return $poolStruct->resource;
            }
        }

        $this->needsRebind = $needsRebind;

        return null;
    }

    private function checkoutNewSocket($uri, $options) {
        if ($this->allowsNewConnection($uri) || $this->needsRebind) {
            $future = new Future($this->reactor);
            $this->initializeNewConnection($future, $uri, $options);
            $this->needsRebind = false;
            return $future->promise();
        } elseif (count($this->queuedSocketRequests) < $this->opMaxQueuedSockets) {
            $future = new Future($this->reactor);
            $this->queuedSocketRequests[$uri][] = [$future, $options];
            return $future->promise();
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

    private function initializeNewConnection(Future $future, $uri, $options) {
        $futureSocket = $this->connector->connect($uri, $options);
        $futureSocket->when(function($error, $socket) use ($future, $uri) {
            if ($error) {
                $future->fail($error);
            } else {
                $this->finalizeNewConnection($future, $uri, $socket);
            }
        });
    }

    private function finalizeNewConnection(Future $future, $uri, $socket) {
        $socketId = (int) $socket;
        $poolStruct = new SocketPoolStruct;
        $poolStruct->id = $socketId;
        $poolStruct->uri = $uri;
        $poolStruct->resource = $socket;
        $poolStruct->isAvailable = false;
        $this->sockets[$uri][$socketId] = $poolStruct;
        $this->socketIdUriMap[$socketId] = $uri;
        $future->succeed($poolStruct->resource);
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
        $poolStruct = $this->sockets[$uri][$socketId];
        if ($poolStruct->idleWatcher) {
            $this->reactor->cancel($poolStruct->idleWatcher);
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
        list($future, $options) = array_shift($this->queuedSocketRequests[$uri]);
        $this->initializeNewConnection($future, $uri, $options);
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
        $poolStruct = $this->sockets[$uri][$socketId];
        $poolStruct->isAvailable = true;

        if (!empty($this->queuedSocketRequests[$uri])) {
            $this->dequeueNextWaitingSocket($uri);
        } elseif ($this->opMsIdleTimeout > 0) {
            $this->initializeIdleTimeout($poolStruct);
        }
    }

    private function initializeIdleTimeout(SocketPoolStruct $poolStruct) {
        if ($poolStruct->idleWatcher === null) {
            $poolStruct->idleWatcher = $this->reactor->once(function() use ($poolStruct) {
                $uri = $poolStruct->uri;
                $socketId = $poolStruct->id;
                $this->unloadSocket($uri, $socketId);
            }, $this->opMsIdleTimeout);
        } else {
            $this->reactor->enable($poolStruct->idleWatcher);
        }
    }

    /**
     * Set socket pool options
     *
     * @param int $option
     * @param mixed $value
     * @throws \DomainException on unknown option
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
            case self::OP_BINDTO:
                $this->options[self::OP_BINDTO] = $value;
                break;
            default:
                throw new \DomainException(
                    sprintf('Unknown option: %s', $option)
                );
        }

        return $this;
    }
}
