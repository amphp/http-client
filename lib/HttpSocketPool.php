<?php

namespace Amp\Artax;

use Amp\Failure;
use Amp\Promise;
use Amp\Socket\RawSocketPool;
use Amp\Socket\SocketPool;
use Amp\Success;
use function Amp\call;

class HttpSocketPool implements SocketPool {
    const OP_PROXY_HTTP = 'amp.artax.httpsocketpool.proxy-http';
    const OP_PROXY_HTTPS = 'amp.artax.httpsocketpool.proxy-https';

    private $socketPool;
    private $tunneler;

    private $options = [
        self::OP_PROXY_HTTP => null,
        self::OP_PROXY_HTTPS => null,
    ];

    public function __construct(SocketPool $sockPool = null, HttpTunneler $tunneler = null) {
        $this->socketPool = $sockPool ?? new RawSocketPool;
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

    private function getUriAuthority(string $uri): string {
        $uriParts = @\parse_url($uri);

        // TODO: Normalize host once LibDNS 2.0 is out
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        return "{$host}:{$port}";
    }

    /** @inheritdoc */
    public function checkout(string $uri, array $options = []): Promise {
        // Normalize away any IPv6 brackets -- socket resolution will handle that
        $uri = \str_replace(['[', ']'], '', $uri);
        $uriParts = @\parse_url($uri);

        $scheme = isset($uriParts['scheme']) ? $uriParts['scheme'] : null;
        $host = $uriParts['host'];
        $port = $uriParts['port'];

        $options = $options ? array_merge($this->options, $options) : $this->options;

        if ($scheme === 'tcp' || $scheme === 'http') {
            $proxy = $options[self::OP_PROXY_HTTP];
        } elseif ($scheme === 'tls' || $scheme === 'https') {
            $proxy = $options[self::OP_PROXY_HTTPS];
        } else {
            return new Failure(new \Error(
                'Either tcp://, tls://, http:// or https:// URI scheme required for HTTP socket checkout'
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

    /** @inheritdoc */
    public function checkin($socket) {
        $this->socketPool->checkin($socket);
    }

    /** @inheritdoc */
    public function clear($socket) {
        $this->socketPool->clear($socket);
    }

    /** @inheritdoc */
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
                break;
        }
    }

    /** @inheritdoc */
    public function getPendingCount(string $uri): int {
        return $this->socketPool->getPendingCount($uri);
    }

    /** @inheritdoc */
    public function getCheckoutCount(string $uri): int {
        return $this->socketPool->getCheckoutCount($uri);
    }
}
