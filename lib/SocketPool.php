<?php

namespace Amp\Artax;

use Amp\Success,
    Amp\Deferred;

class SocketPool {
    const OP_HOST_CONNECTION_LIMIT = 'op.host-conn-limit';
    const OP_MS_IDLE_TIMEOUT = 'op.ms-idle-timeout';
    const OP_MS_CONNECT_TIMEOUT = "timeout";
    const OP_BINDTO = "bind_to";

    private $sockets = [];
    private $queuedSocketRequests = [];
    private $socketIdUriMap = [];
    private $pendingSockets = [];
    private $options = [
        self::OP_HOST_CONNECTION_LIMIT => 8,
        self::OP_MS_IDLE_TIMEOUT => 10000,
        self::OP_MS_CONNECT_TIMEOUT => 30000,
        self::OP_BINDTO => '',
    ];
    private $needsRebind;

    /**
     * Checkout a socket from the specified URI authority
     *
     * The resulting socket resource should be checked back in via SocketPool::checkin() once the
     * calling code is finished with the stream (even if the socket has been closed). Failure to
     * checkin sockets will result in memory leaks and socket queue blockage.
     *
     * @param string $uri A string of the form somedomain.com:80 or 192.168.1.1:443
     * @param array $options
     * @return \Amp\Promise Returns a promise that resolves to a socket once a connection is available
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
                \Amp\disable($poolStruct->idleWatcher);
                return $poolStruct->resource;
            } elseif ($bindToIp) {
                $needsRebind = true;
            } else {
                $poolStruct->isAvailable = false;
                \Amp\disable($poolStruct->idleWatcher);

                return $poolStruct->resource;
            }
        }

        $this->needsRebind = $needsRebind;

        return null;
    }

    private function checkoutNewSocket($uri, $options) {
        $needsRebind = $this->needsRebind;
        $this->needsRebind = null;
        $promisor = new Deferred;

        if ($this->allowsNewConnection($uri, $options) || $needsRebind) {
            $this->initializeNewConnection($promisor, $uri, $options);
        } else {
            $this->queuedSocketRequests[$uri][] = [$promisor, $uri, $options];
        }

        return $promisor->promise();
    }

    private function allowsNewConnection($uri, $options) {
        $maxConnsPerHost = $options[self::OP_HOST_CONNECTION_LIMIT];

        if ($maxConnsPerHost <= 0) {
            return true;
        }

        $pendingCount = isset($this->pendingSockets[$uri]) ? $this->pendingSockets[$uri] : 0;
        $existingCount = isset($this->sockets[$uri]) ? count($this->sockets[$uri]) : 0;
        $totalCount = $pendingCount + $existingCount;

        if ($totalCount < $maxConnsPerHost) {
            return true;
        }

        return false;
    }

    private function initializeNewConnection(Deferred $promisor, $uri, $options) {
        $this->pendingSockets[$uri] = isset($this->pendingSockets[$uri])
            ? $this->pendingSockets[$uri] + 1
            : 1;
        $futureSocket = \Amp\Socket\connect($uri, $options);
        $futureSocket->when(function($error, $socket) use ($promisor, $uri, $options) {
            if ($error) {
                $promisor->fail($error);
            } else {
                $this->finalizeNewConnection($promisor, $uri, $socket, $options);
            }
        });
    }

    private function finalizeNewConnection(Deferred $promisor, $uri, $socket, $options) {
        if (--$this->pendingSockets[$uri] === 0) {
            unset($this->pendingSockets[$uri]);
        }
        $socketId = (int) $socket;
        $poolStruct = new SocketPoolStruct;
        $poolStruct->id = $socketId;
        $poolStruct->uri = $uri;
        $poolStruct->resource = $socket;
        $poolStruct->isAvailable = false;
        $poolStruct->msIdleTimeout = $options[self::OP_MS_IDLE_TIMEOUT];
        $this->sockets[$uri][$socketId] = $poolStruct;
        $this->socketIdUriMap[$socketId] = $uri;
        $promisor->succeed($poolStruct->resource);

        if (empty($this->queuedSocketRequests[$uri])) {
            unset($this->queuedSocketRequests[$uri]);
        }
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
        if (!isset($this->sockets[$uri][$socketId])) {
            return;
        }

        $poolStruct = $this->sockets[$uri][$socketId];
        if ($poolStruct->idleWatcher) {
            \Amp\cancel($poolStruct->idleWatcher);
        }
        unset(
            $this->sockets[$uri][$socketId],
            $this->socketIdUriMap[$socketId]
        );

        if (empty($this->sockets[$uri])) {
            unset($this->sockets[$uri][$socketId]);
        }

        if (!empty($this->queuedSocketRequests[$uri])) {
            $this->dequeueNextWaitingSocket($uri);
        }
    }

    private function dequeueNextWaitingSocket($uri) {
        $queueStruct = current($this->queuedSocketRequests[$uri]);
        list($promisor, $uri, $options) = $queueStruct;

        if ($socket = $this->checkoutExistingSocket($uri, $options)) {
            array_shift($this->queuedSocketRequests[$uri]);
            $promisor->succeed($socket);
            return;
        }

        if ($this->allowsNewConnection($uri, $options)) {
            array_shift($this->queuedSocketRequests[$uri]);
            $this->initializeNewConnection($promisor, $uri, $options);
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
        } elseif ($poolStruct->msIdleTimeout > 0) {
            $this->initializeIdleTimeout($poolStruct);
        }
    }

    private function initializeIdleTimeout(SocketPoolStruct $poolStruct) {
        if (isset($poolStruct->idleWatcher)) {
            \Amp\enable($poolStruct->idleWatcher);
        } else {
            $poolStruct->idleWatcher = \Amp\once(function() use ($poolStruct) {
                $this->unloadSocket($poolStruct->uri, $poolStruct->id);
            }, $poolStruct->msIdleTimeout);
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
