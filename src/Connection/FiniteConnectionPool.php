<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\coroutine;

final class FiniteConnectionPool implements ConnectionPool
{
    use ForbidSerialization;

    /** @var int */
    private $maxHttp1Connections;

    /** @var int */
    private $maxHttp2Connections;

    /** @var ConnectionFactory */
    private $connectionFactory;

    /** @var Promise[][] */
    private $connections = [];

    /** @var Deferred[][] */
    private $waiting = [];

    /** @var bool[] */
    private $waitForPriorConnection = [];

    /** @var int */
    private $totalConnectionAttempts = 0;

    /** @var int */
    private $totalStreamRequests = 0;

    /** @var int */
    private $openConnectionCount = 0;

    /** @var callable */
    private $requestToKeyMapper;

    /**
     * Create a connection pool that limits the number of connections per authority.
     *
     * @param int                    $maxHttp1Connections Maximum number of HTTP/2 connections allowed to a single authority.
     * @param int                    $maxHttp2Connections Maximum number of HTTP/1.x connections allowed to a single authority.
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byAuthority(
        int $maxHttp1Connections = 6,
        int $maxHttp2Connections = 1,
        ?ConnectionFactory $connectionFactory = null
    ): self {
        return new self(static function (Request $request): string {
            $uri = $request->getUri();
            $scheme = $uri->getScheme();

            $isHttps = $scheme === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $host = $uri->getHost();
            $port = $uri->getPort() ?? $defaultPort;

            $authority = $host . ':' . $port;
            return $scheme . '://' . $authority;
        }, $maxHttp1Connections, $maxHttp2Connections, $connectionFactory);
    }

    /**
     * Create a connection pool that limits the number of connections per host.
     *
     * @param int                    $maxHttp1Connections Maximum number of HTTP/2 connections allowed to a single host.
     * @param int                    $maxHttp2Connections Maximum number of HTTP/1.x connections allowed to a single host.
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byHost(
        int $maxHttp1Connections = 6,
        int $maxHttp2Connections = 1,
        ?ConnectionFactory $connectionFactory = null
    ): self {
        return new self(static function (Request $request): string {
            return $request->getUri()->getHost();
        }, $maxHttp1Connections, $maxHttp2Connections, $connectionFactory);
    }

    /**
     * Create a connection pool that limits the number of connections based on a static key.
     *
     * @param int                    $maxHttp1Connections Maximum number of HTTP/2 connections allowed.
     * @param int                    $maxHttp2Connections Maximum number of HTTP/1.x connections allowed.
     * @param string                 $key
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byStaticKey(
        int $maxHttp1Connections = 6,
        int $maxHttp2Connections = 1,
        string $key = '',
        ?ConnectionFactory $connectionFactory = null
    ): self {
        return new self(static function () use ($key): string {
            return $key;
        }, $maxHttp1Connections, $maxHttp2Connections, $connectionFactory);
    }

    /**
     * Create a connection pool that limits the number of connections using a custom key provided by the callback.
     *
     * @param callable               $requestToKeyMapper Function accepting a Request object and returning a string.
     * @param int                    $maxHttp1Connections Maximum number of HTTP/2 connections allowed to a single authority.
     * @param int                    $maxHttp2Connections Maximum number of HTTP/1.x connections allowed to a single authority.
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byCustomKey(
        callable $requestToKeyMapper,
        int $maxHttp1Connections = 6,
        int $maxHttp2Connections = 1,
        ?ConnectionFactory $connectionFactory = null
    ): self {
        return new self($requestToKeyMapper, $maxHttp1Connections, $maxHttp2Connections, $connectionFactory);
    }

    private function __construct(
        callable $requestToKeyMapper,
        int $maxHttp1Connections,
        int $maxHttp2Connections,
        ?ConnectionFactory $connectionFactory = null
    ) {
        if ($maxHttp1Connections < 1) {
            throw new \Error('The number of max HTTP/1.x connections per key must be greater than 0');
        }

        if ($maxHttp1Connections < 1) {
            throw new \Error('The number of max HTTP/2 connections per key must be greater than 0');
        }

        $this->maxHttp1Connections = $maxHttp1Connections;
        $this->maxHttp2Connections = $maxHttp2Connections;
        $this->requestToKeyMapper = $requestToKeyMapper;
        $this->connectionFactory = $connectionFactory ?? new DefaultConnectionFactory;
    }

    public function __clone()
    {
        $this->connections = [];
        $this->totalConnectionAttempts = 0;
        $this->totalStreamRequests = 0;
        $this->openConnectionCount = 0;
    }

    public function getTotalConnectionAttempts(): int
    {
        return $this->totalConnectionAttempts;
    }

    public function getTotalStreamRequests(): int
    {
        return $this->totalStreamRequests;
    }

    public function getOpenConnectionCount(): int
    {
        return $this->openConnectionCount;
    }

    public function getStream(Request $request, CancellationToken $cancellation): Promise
    {
        return call(function () use ($request, $cancellation) {
            $key = ($this->requestToKeyMapper)($request);

            /** @var Stream $stream */
            $stream = yield from $this->fetchStream($key, $request, $cancellation);

            return HttpStream::fromStream(
                $stream,
                coroutine(function (Request $request, CancellationToken $cancellationToken) use (
                    $stream,
                    $key
                ) {
                    try {
                        /** @var Response $response */
                        $response = yield $stream->request($request, $cancellationToken);

                        // await response being completely received
                        $response->getTrailers()->onResolve(function () use ($key): void {
                            $this->release($key);
                        });
                    } catch (\Throwable $e) {
                        $this->release($key);
                        throw $e;
                    }

                    return $response;
                }),
                function () use ($key): void {
                    $this->release($key);
                }
            );
        });
    }

    private function fetchStream(string $key, Request $request, CancellationToken $cancellation): \Generator
    {
        $this->totalStreamRequests++;

        $isHttps = $request->getUri()->getScheme() === 'https';

        $connections = $this->connections[$key] ?? new \ArrayObject;

        do {
            foreach ($connections as $connectionPromise) {
                \assert($connectionPromise instanceof Promise);

                try {
                    if ($isHttps && ($this->waitForPriorConnection[$key] ?? true)) {
                        // Wait for first successful connection if using a secure connection (maybe we can use HTTP/2).
                        $connection = yield $connectionPromise;
                    } else {
                        $connection = yield Promise\first([$connectionPromise, new Success]);
                        if ($connection === null) {
                            continue;
                        }
                    }
                } catch (\Exception $exception) {
                    continue; // Ignore cancellations and errors of other requests.
                }

                \assert($connection instanceof Connection);

                if (!\array_intersect($request->getProtocolVersions(), $connection->getProtocolVersions())) {
                    continue; // Connection does not support any of the requested protocol versions.
                }

                $stream = yield $connection->getStream($request);

                if ($stream === null) {
                    continue; // No stream available for the given request.
                }

                return $stream;
            }

            if ($this->shouldMakeNewConnection($key)) {
                break;
            }

            $this->waiting[$key][] = $deferred = new Deferred;
            yield $deferred->promise();
        } while (true);

        $this->totalConnectionAttempts++;

        $connectionPromise = $this->connectionFactory->create($request, $cancellation);

        $hash = \spl_object_hash($connectionPromise);
        $this->connections[$key] = $this->connections[$key] ?? new \ArrayObject;
        $this->connections[$key][$hash] = $connectionPromise;

        try {
            $connection = yield $connectionPromise;
            $this->openConnectionCount++;

            \assert($connection instanceof Connection);
        } catch (\Throwable $exception) {
            $this->dropConnection($key, $hash);

            throw $exception;
        }

        if ($isHttps) {
            $this->waitForPriorConnection[$key] = \in_array('2', $connection->getProtocolVersions());
        }

        $connection->onClose(function () use ($key, $hash): void {
            $this->openConnectionCount--;
            $this->dropConnection($key, $hash);
        });

        $stream = yield $connection->getStream($request);

        \assert($stream instanceof Stream); // New connection must always resolve with a Stream instance.

        return $stream;
    }

    private function shouldMakeNewConnection(string $key): bool
    {
        $count = \count($this->connections[$key] ?? []);

        if ($this->waitForPriorConnection[$key] ?? false) {
            return $count < $this->maxHttp2Connections;
        }

        return $count < $this->maxHttp1Connections;
    }

    private function release(string $key): void
    {
        if (!isset($this->waiting[$key])) {
            return;
        }

        $deferred = \array_shift($this->waiting[$key]);

        if (empty($this->waiting[$key])) {
            unset($this->waiting[$key]);
        }

        $deferred->resolve();
    }

    private function dropConnection(string $key, string $connectionHash): void
    {
        unset($this->connections[$key][$connectionHash]);

        if (empty($this->connections[$key])) {
            unset($this->connections[$key], $this->waitForPriorConnection[$key]);
        }

        $this->release($key);
    }
}
