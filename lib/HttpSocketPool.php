<?php

namespace Amp\Artax;

use Amp\Failure;
use Amp\Deferred;

class HttpSocketPool {
    const OP_PROXY_HTTP = 'op.proxy-http';
    const OP_PROXY_HTTPS = 'op.proxy-https';
    const OP_PROXY_HTTP_AUTH = 'op.proxy-https-auth';
    const OP_PROXY_HTTPS_AUTH = 'op.proxy-https-auth';

    private $sockPool;
    private $tunneler;
    private $options = [
        self::OP_PROXY_HTTP => null,
        self::OP_PROXY_HTTPS => null,
        self::OP_PROXY_HTTP_AUTH => null,
        self::OP_PROXY_HTTPS_AUTH => null,
    ];

    public function __construct(SocketPool $sockPool = null, HttpTunneler $tunneler = null) {
        $this->sockPool = $sockPool ?: new SocketPool();
        $this->tunneler = $tunneler ?: new HttpTunneler();
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
     * @return \Amp\Promise
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
            $proxyAuth = $options[self::OP_PROXY_HTTP_AUTH];
        } elseif ($scheme === 'https') {
            $proxy = $options[self::OP_PROXY_HTTPS];
            $proxyAuth = $options[self::OP_PROXY_HTTPS_AUTH];
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
        $promisor = new Deferred;
        $futureCheckout = $this->sockPool->checkout($uri, $options);
        $futureCheckout->when(function($error, $socket) use ($promisor, $proxy, $authority, $proxyAuth) {
            if ($error) {
                $promisor->fail($error);
            } elseif ($proxy) {
                $this->tunnelThroughProxy($promisor, $socket, $authority, $proxyAuth);
            } else {
                $promisor->succeed($socket);
            }
        });

        return $promisor->promise();
    }

    private function tunnelThroughProxy(Deferred $promisor, $socket, $authority, $proxyAuth) {
        if (empty(stream_context_get_options($socket)['artax*']['is_tunneled'])) {
            $futureTunnel = $this->tunneler->tunnel($socket, $authority, $proxyAuth);
            $futureTunnel->when(function($error) use ($promisor, $socket) {
                if ($error) {
                    $promisor->fail($error);
                } else {
                    $promisor->succeed($socket);
                }
            });
        } else {
            $promisor->succeed($socket);
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
                return $this->sockPool->setOption($option, $value);
        }

        return $this;
    }
}
