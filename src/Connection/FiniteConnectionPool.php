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

    /**
     * Create a connection pool that limits the number of connections per authority.
     *
     * @param int                    $maxHttp1Connections Maximum number of HTTP/1.x connections allowed to a single authority.
     * @param int                    $maxHttp2Connections Maximum number of HTTP/2 connections allowed to a single authority.
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byAuthority(
        int $maxHttp1Connections = 6,
        int $maxHttp2Connections = 1,
        ?ConnectionFactory $connectionFactory = null
    ): self {
        return new self(
            $maxHttp1Connections,
            $maxHttp2Connections,
            $connectionFactory
        );
    }

    private static function formatUri(Request $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();

        $isHttps = $scheme === 'https';
        $defaultPort = $isHttps ? 443 : 80;

        $host = $uri->getHost();
        $port = $uri->getPort() ?? $defaultPort;

        $authority = $host . ':' . $port;
        return $scheme . '://' . $authority;
    }

    private function __construct(
        int $maxHttp1Connections,
        int $maxHttp2Connections,
        ?ConnectionFactory $connectionFactory = null
    ) {
        if ($maxHttp1Connections < 1) {
            throw new \Error('The number of max HTTP/1.x connections per key must be greater than 0');
        }

        if ($maxHttp2Connections < 1) {
            throw new \Error('The number of max HTTP/2 connections per key must be greater than 0');
        }

        $this->maxHttp1Connections = $maxHttp1Connections;
        $this->maxHttp2Connections = $maxHttp2Connections;
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
            $this->totalStreamRequests++;

            $uri = self::formatUri($request);

            /** @var Stream $stream */
            [$connection, $stream] = yield from $this->fetchStream($uri, $request, $cancellation);

            return HttpStream::fromStream(
                $stream,
                coroutine(function (Request $request, CancellationToken $cancellationToken) use (
                    $connection,
                    $stream,
                    $uri
                ) {
                    try {
                        /** @var Response $response */
                        $response = yield $stream->request($request, $cancellationToken);
                    } catch (\Throwable $e) {
                        $this->release($connection, $uri);
                        throw $e;
                    }

                    // await response being completely received
                    $response->getTrailers()->onResolve(function () use ($connection, $uri): void {
                        $this->release($connection, $uri);
                    });

                    return $response;
                }),
                function () use ($connection, $uri): void {
                    $this->release($connection, $uri);
                }
            );
        });
    }

    private function fetchStream(string $uri, Request $request, CancellationToken $cancellation): \Generator
    {
        $isHttps = $request->getUri()->getScheme() === 'https';

        do {
            $connections = $this->connections[$uri] ?? new \ArrayObject;

            do {
                foreach ($connections as $connectionPromise) {
                    \assert($connectionPromise instanceof Promise);

                    try {
                        if ($isHttps && ($this->waitForPriorConnection[$uri] ?? true)) {
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

                    return [$connection, $stream];
                }

                $deferred = new Deferred;
                $deferredId = \spl_object_id($deferred);

                $this->waiting[$uri][$deferredId] = $deferred;
                $deferredPromise = $deferred->promise();
                $deferredPromise->onResolve(function () use ($uri, $deferredId): void {
                    unset($this->waiting[$uri][$deferredId]);
                    if (empty($this->waiting[$uri])) {
                        unset($this->waiting[$uri]);
                    }
                });

                if ($this->shouldMakeNewConnection($uri)) {
                    break;
                }

                yield $deferredPromise;
            } while (true);

            $this->totalConnectionAttempts++;

            $connectionPromise = $this->connectionFactory->create($request, $cancellation);

            $connectionId = \spl_object_id($connectionPromise);
            $this->connections[$uri] = $this->connections[$uri] ?? new \ArrayObject;
            $this->connections[$uri][$connectionId] = $connectionPromise;

            $connectionPromise->onResolve(function (?\Throwable $exception, ?Connection $connection) use (
                &$deferred,
                $uri,
                $connectionId,
                $isHttps
            ): void {
                if ($exception) {
                    $this->dropConnection($uri, $connectionId);
                    if ($deferred !== null) {
                        $deferred->fail($exception); // Fail Deferred so Promise\first() below fails.
                    }
                    return;
                }

                $this->openConnectionCount++;

                if ($isHttps) {
                    $this->waitForPriorConnection[$uri] = \in_array('2', $connection->getProtocolVersions(), true);
                }

                $connection->onClose(function () use ($uri, $connectionId): void {
                    $this->openConnectionCount--;
                    $this->dropConnection($uri, $connectionId);
                });
            });

            $connection = yield Promise\first([$connectionPromise, $deferredPromise]);

            $deferred = null; // Null reference so connection promise handler does not double-resolve the Deferred.
            unset($this->waiting[$uri][$deferredId]); // Deferred no longer needed for this request.

            \assert($connection instanceof Connection);

            $stream = yield $connection->getStream($request);

            if ($stream === null) {
                continue; // Reused connection did not have a stream.
            }

            \assert($stream instanceof Stream);

            return [$connection, $stream];
        } while (true);
    }

    private function shouldMakeNewConnection(string $uri): bool
    {
        $count = \count($this->connections[$uri] ?? []);

        if ($this->waitForPriorConnection[$uri] ?? false) {
            return $count < $this->maxHttp2Connections;
        }

        return $count < $this->maxHttp1Connections;
    }

    private function release(Connection $connection, string $uri): void
    {
        if (empty($this->waiting[$uri])) {
            return;
        }

        $deferred = \array_shift($this->waiting[$uri]);
        $deferred->resolve($connection);
    }

    private function dropConnection(string $uri, int $connectionId): void
    {
        unset($this->connections[$uri][$connectionId]);

        if (empty($this->connections[$uri])) {
            unset($this->connections[$uri], $this->waitForPriorConnection[$uri]);
        }
    }
}
