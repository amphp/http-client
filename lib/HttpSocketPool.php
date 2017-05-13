<?php

namespace Amp\Artax;

use Amp\Failure;
use Amp\Promise;
use Amp\Socket\SocketPool;
use function Amp\call;
use Amp\Success;

class HttpSocketPool {
    const OP_PROXY_HTTP = 'amp.artax.httpsocketpool.proxy-http';
    const OP_PROXY_HTTPS = 'amp.artax.httpsocketpool.proxy-https';

    private $socketPool;
    private $tunneler;

    private $options = [
        self::OP_PROXY_HTTP => null,
        self::OP_PROXY_HTTPS => null,
    ];

    public function __construct(SocketPool $sockPool = null, HttpTunneler $tunneler = null) {
        $this->socketPool = $sockPool ?? new SocketPool;
        $this->tunneler = $tunneler ?? new HttpTunneler;
        $this->autoDetectProxySettings();
    }

    private function autoDetectProxySettings() {
        // See CVE-2016-5385, due to (emulation of) header copying with PHP web SAPIs into HTTP_* variables,
        // HTTP_PROXY can be set by an user to any value he wants by setting the Proxy header.
        // Mitigate the vulnerability by only allowing CLI SAPIs to use HTTP(S)_PROXY environment variables.
        if (PHP_SAPI !== "cli" && PHP_SAPI !== "phpdbg" && PHP_SAPI !== "embed") {
            return;
        }

        if (($httpProxy = \getenv('http_proxy')) || ($httpProxy = \getenv('HTTP_PROXY'))) {
            $this->options[self::OP_PROXY_HTTP] = $this->getUriAuthority($httpProxy);
        }

        if (($httpsProxy = \getenv('https_proxy')) || ($httpsProxy = \getenv('HTTPS_PROXY'))) {
            $this->options[self::OP_PROXY_HTTPS] = $this->getUriAuthority($httpsProxy);
        }
    }

    private function getUriAuthority($uri): string {
        $uriParts = @\parse_url(\strtolower($uri));
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        return "{$host}:{$port}";
    }

    /**
     * I give you a URI, you promise me a socket at some point in the future.
     *
     * @param string $uri
     * @param array  $options
     *
     * @return Promise
     */
    public function checkout($uri, array $options = []): Promise {
        // Normalize away any IPv6 brackets -- socket resolution will handle that
        $uri = \str_replace(['[', ']'], '', $uri);
        $uriParts = @\parse_url($uri);

        $scheme = isset($uriParts['scheme']) ? $uriParts['scheme'] : null;
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        $options = $options ? array_merge($this->options, $options) : $this->options;

        if ($scheme === 'http') {
            $proxy = $options[self::OP_PROXY_HTTP];
        } elseif ($scheme === 'https') {
            $proxy = $options[self::OP_PROXY_HTTPS];
        } else {
            return new Failure(new \Error(
                'Either http:// or https:// URI scheme required for HTTP socket checkout'
            ));
        }

        // The underlying TCP pool will ignore the URI fragment when connecting but retain it in the
        // name when tracking hostname connection counts. This allows us to expose host connection
        // limits transparently even when connecting through a proxy.
        $authority = "{$host}:{$port}";
        $uri = $proxy ? "tcp://{$proxy}#{$authority}" : "tcp://{$authority}";

        return call(function () use ($uri, $options, $proxy, $authority) {
            $socket = yield $this->socketPool->checkout($uri, $options);

            if ($proxy) {
                yield $this->tunnelThroughProxy($socket, $authority);
            }

            return $socket;
        });
    }

    private function tunnelThroughProxy($socket, $authority): Promise {
        if (empty(stream_context_get_options($socket)['artax*']['is_tunneled'])) {
            return $this->tunneler->tunnel($socket, $authority);
        }

        return new Success;
    }

    /**
     * Checkin a previously checked-out socket into the pool again.
     *
     * @param resource $socket
     */
    public function checkin($socket) {
        $this->socketPool->checkin($socket);
    }

    /**
     * Clear a previously checked-out socket from the pool.
     *
     * @param resource $socket
     */
    public function clear($socket) {
        $this->socketPool->clear($socket);
    }

    /**
     * Set a pool option.
     *
     * @param string $option
     * @param mixed      $value
     *
     * @throws \Error on unknown option
     */
    public function setOption(string $option, $value) {
        switch ($option) {
            case self::OP_PROXY_HTTP:
                $this->options[self::OP_PROXY_HTTP] = (string) $value;
                break;
            case self::OP_PROXY_HTTPS:
                $this->options[self::OP_PROXY_HTTPS] = (string) $value;
                break;
            default:
                $this->socketPool->setOption($option, $value);
        }
    }
}
