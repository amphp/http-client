<?php

namespace Artax;

use Alert\Reactor,
    After\Failure,
    After\Success,
    After\Deferred;

class HttpSocketPool implements SocketPool {
    const OP_PROXY_HTTP = 'op.proxy-http';
    const OP_PROXY_HTTPS = 'op.proxy-https';

    private $reactor;
    private $tcpPool;
    private $tunneler;
    private $options = [
        self::OP_PROXY_HTTP => null,
        self::OP_PROXY_HTTPS => null,
    ];

    public function __construct(Reactor $reactor, TcpPool $tcpPool = null, HttpTunneler $tunneler = null) {
        $this->reactor = $reactor;
        $this->tcpPool = $tcpPool ?: new TcpPool($reactor);
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
        $scheme = isset($uriParts['scheme']) ? $uriParts['scheme'] : null;
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        return "{$host}:{$port}";
    }

    /**
     *
     * @return After\Promise
     */
    public function checkout($uri, array $options = []) {
        // Normalize away any IPv6 brackets -- DNS resolution will handle those distinctions for us
        $uri = strtolower(str_replace(['[', ']'], '', $uri));
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
        $deferred = new Deferred;
        $deferredCheckout = $this->tcpPool->checkout($uri);
        $deferredCheckout->onResolve(function($error, $socket) use ($deferred, $proxy, $authority) {
            if ($error) {
                $deferred->fail($error);
            } elseif ($proxy) {
                $this->tunnelThroughProxy($deferred, $socket, $authority);
            } else {
                $deferred->succeed($socket);
            }
        });

        return $deferred->promise();
    }

    private function tunnelThroughProxy(Deferred $deferred, $socket, $authority) {
        if (empty(stream_context_get_options($socket)['artax*']['is_tunneled'])) {
            $deferredTunnel = $this->tunneler->tunnel($socket, $authority);
            $deferredTunnel->onResolve(function($error, $result) use ($deferred, $socket) {
                if ($error) {
                    $deferred->fail($error);
                } else {
                    $deferred->succeed($socket);
                }
            });
        } else {
            $deferred->succeed($socket);
        }
    }

    /**
     *
     */
    public function checkin($socket) {
        $this->tcpPool->checkin($socket);
    }

    /**
     *
     */
    public function clear($socket) {
        $this->tcpPool->clear($socket);
    }

    /**
     *
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_HTTP_PROXY_ADDR:
                $this->options[self::OP_HTTP_PROXY_ADDR] = (string) $value;
                break;
            case self::OP_HTTPS_PROXY_ADDR:
                $this->options[self::OP_HTTPS_PROXY_ADDR] = (string) $value;
                break;
            default:
                throw new \DomainException(
                    sprintf("Unknown option: %s", $option)
                );
        }

        return $this;
    }
}
