<?php

namespace Artax;

use Alert\Reactor;
use After\Failure;
use After\Future;

class HttpSocketPool {
    const OP_PROXY_HTTP = 'op.proxy-http';
    const OP_PROXY_HTTPS = 'op.proxy-https';

    private $reactor;
    private $sockPool;
    private $tunneler;
    private $options = [
        self::OP_PROXY_HTTP => null,
        self::OP_PROXY_HTTPS => null,
    ];

    public function __construct(Reactor $reactor, SocketPool $sockPool = null, HttpTunneler $tunneler = null) {
        $this->reactor = $reactor;
        $this->sockPool = $sockPool ?: new SocketPool($reactor);
        $this->tunneler = $tunneler ?: new HttpTunneler($reactor);
        $this->autoDetectProxySettings();
    }

    private function autoDetectProxySettings() {
        if (($httpProxy = getenv('http_proxy')) || ($httpProxy = getenv('HTTP_PROXY'))) {
            $this->options[self::OP_PROXY_HTTP] = $this->getUriAuthority($httpProxy);
        }
        if (($httpsProxy = getenv('https_proxy')) || ($httpsProxy = getenv('HTTPS_PROXY'))) {
            $this->options[self::OP_PROXY_HTTPS] = $this->getUriAuthority($httpsProxy);
        }
    }

    private function getUriAuthority($uri) {
        $uriParts = @parse_url(strtolower($uri));
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        return "{$host}:{$port}";
    }

    /**
     * I give you a URI, you promise me a socket at some point in the future
     *
     * @param string $uri
     * @param array $options
     * @return \After\Promise
     */
    public function checkout($uri, array $options = []) {
        // Normalize away any IPv6 brackets -- socket resolution will handle that
        $uri = str_replace(['[', ']'], '', $uri);
        $uriParts = @parse_url($uri);
        $scheme = isset($uriParts['scheme']) ? $uriParts['scheme'] : null;
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        $options = $options ? array_merge($this->options, $options) : $this->options;

        if ($scheme === 'http') {
            $proxy = $options[self::OP_PROXY_HTTP];
        } elseif ($scheme === 'https') {
            $proxy = $options[self::OP_PROXY_HTTPS];
        } else {
            return new Failure(new \DomainException(
                'Either http:// or https:// URI scheme required for HTTP socket checkout'
            ));
        }

        // The underlying TCP pool will ignore the URI fragment when connecting but retain it in the
        // name when tracking hostname connection counts. This allows us to expose host connection
        // limits transparently even when connecting through a proxy.
        $authority = "{$host}:{$port}";
        $uri = $proxy ? "tcp://{$proxy}#{$authority}" : "tcp://{$authority}";
        $future = new Future($this->reactor);
        $futureCheckout = $this->sockPool->checkout($uri, $options);
        $futureCheckout->when(function($error, $socket) use ($future, $proxy, $authority) {
            if ($error) {
                $future->fail($error);
            } elseif ($proxy) {
                $this->tunnelThroughProxy($future, $socket, $authority);
            } else {
                $future->succeed($socket);
            }
        });

        return $future->promise();
    }

    private function tunnelThroughProxy(Future $future, $socket, $authority) {
        if (empty(stream_context_get_options($socket)['artax*']['is_tunneled'])) {
            $futureTunnel = $this->tunneler->tunnel($socket, $authority);
            $futureTunnel->when(function($error) use ($future, $socket) {
                if ($error) {
                    $future->fail($error);
                } else {
                    $future->succeed($socket);
                }
            });
        } else {
            $future->succeed($socket);
        }
    }

    /**
     * Checkin a previously checked-out socket
     *
     * @param resource $socket
     * @return self
     */
    public function checkin($socket) {
        $this->sockPool->checkin($socket);

        return $this;
    }

    /**
     * Clear a previously checked-out socket from the pool
     *
     * @param resource $socket
     * @return self
     */
    public function clear($socket) {
        $this->sockPool->clear($socket);

        return $this;
    }

    /**
     * Set a socket pool option
     *
     * @param int|string $option
     * @param mixed $value
     * @throws \DomainException on unknown option
     * @return self
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_PROXY_HTTP:
                $this->options[self::OP_PROXY_HTTP] = (string) $value;
                break;
            case self::OP_PROXY_HTTPS:
                $this->options[self::OP_PROXY_HTTPS] = (string) $value;
                break;
            default:
                throw new \DomainException(
                    sprintf("Unknown option: %s", $option)
                );
        }

        return $this;
    }
}
