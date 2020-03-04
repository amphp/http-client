<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\MultiReasonException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\coroutine;

final class ConnectionLimitingPool implements ConnectionPool
{
    use ForbidSerialization;

    /** @var int */
    private $connectionLimit;

    /** @var ConnectionFactory */
    private $connectionFactory;

    /** @var Promise[][] */
    private $connections = [];

    /** @var int[] */
    private $activeRequestCounts = [];

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
     * @param int                    $connectionLimit Maximum number of connections allowed to a single authority.
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byAuthority(int $connectionLimit, ?ConnectionFactory $connectionFactory = null): self
    {
        return new self($connectionLimit, $connectionFactory);
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

    private function __construct(int $connectionLimit, ?ConnectionFactory $connectionFactory = null)
    {
        if ($connectionLimit < 1) {
            throw new \Error('The connection limit must be greater than 0');
        }

        $this->connectionLimit = $connectionLimit;
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
            [$connection, $stream] = yield from $this->getStreamFor($uri, $request, $cancellation);

            $connectionId = \spl_object_id($connection);
            $this->activeRequestCounts[$connectionId] = ($this->activeRequestCounts[$connectionId] ?? 0) + 1;

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
                        $this->onReadyConnection($connection, $uri);
                        throw $e;
                    }

                    // await response being completely received
                    $response->getTrailers()->onResolve(function () use ($connection, $uri): void {
                        $this->onReadyConnection($connection, $uri);
                    });

                    return $response;
                }),
                function () use ($connection, $uri): void {
                    $this->onReadyConnection($connection, $uri);
                }
            );
        });
    }

    private function getStreamFor(string $uri, Request $request, CancellationToken $cancellation): \Generator
    {
        $isHttps = $request->getUri()->getScheme() === 'https';

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

                $stream = yield $this->getStreamFromConnection($connection, $request);

                if ($stream === null) {
                    $connectionId = \spl_object_id($connection);
                    if (!$this->isAdditionalConnectionAllowed($uri)
                        && ($this->activeRequestCounts[$connectionId] ?? 0) === 0
                    ) {
                        // No additional connections allowed, but this connection is idle and unsuited for this request.
                        $connection->close();
                        break;
                    }

                    continue; // No stream available for the given request.
                }

                return [$connection, $stream];
            }

            $deferred = new Deferred;
            $deferredId = \spl_object_id($deferred);

            $this->waiting[$uri][$deferredId] = $deferred;
            $deferredPromise = $deferred->promise();
            $deferredPromise->onResolve(function () use ($uri, $deferredId): void {
                $this->removeWaiting($uri, $deferredId);
            });

            if ($this->isAdditionalConnectionAllowed($uri)) {
                break;
            }

            $connection = yield $deferredPromise;

            \assert($connection instanceof Connection);

            $stream = yield $this->getStreamFromConnection($connection, $request);

            if ($stream === null) {
                continue; // Wait for a different connection to become available.
            }

            return [$connection, $stream];
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

        try {
            $connection = yield Promise\first([$connectionPromise, $deferredPromise]);
        } catch (MultiReasonException $exception) {
            [$exception] = $exception->getReasons(); // The first reason is why the connection failed.
            throw $exception;
        }

        $deferred = null; // Null reference so connection promise handler does not double-resolve the Deferred.
        $this->removeWaiting($uri, $deferredId); // Deferred no longer needed for this request.

        \assert($connection instanceof Connection);

        $stream = yield $this->getStreamFromConnection($connection, $request);

        if ($stream === null) {
            // Reused connection did not have an available stream for the given request.
            $connection = yield $connectionPromise; // Wait for new connection request instead.

            $stream = yield $this->getStreamFromConnection($connection, $request);

            if ($stream === null) {
                // Other requests used the new connection first, so we need to go around again.
                return yield from $this->getStreamFor($uri, $request, $cancellation);
            }
        }

        return [$connection, $stream];
    }

    private function getStreamFromConnection(Connection $connection, Request $request): Promise
    {
        if (!\array_intersect($request->getProtocolVersions(), $connection->getProtocolVersions())) {
            return new Success; // Connection does not support any of the requested protocol versions.
        }

        return $connection->getStream($request);
    }

    private function isAdditionalConnectionAllowed(string $uri): bool
    {
        return \count($this->connections[$uri] ?? []) < $this->connectionLimit;
    }

    private function onReadyConnection(Connection $connection, string $uri): void
    {
        $connectionId = \spl_object_id($connection);
        if (isset($this->activeRequestCounts[$connectionId])) {
            $this->activeRequestCounts[$connectionId]--;
        }

        if (empty($this->waiting[$uri])) {
            return;
        }

        $deferred = \reset($this->waiting[$uri]);
        // Deferred is removed from waiting list in onResolve callback attached above.
        $deferred->resolve($connection);
    }

    private function removeWaiting(string $uri, int $deferredId): void
    {
        unset($this->waiting[$uri][$deferredId]);
        if (empty($this->waiting[$uri])) {
            unset($this->waiting[$uri]);
        }
    }

    private function dropConnection(string $uri, int $connectionId): void
    {
        unset($this->connections[$uri][$connectionId], $this->activeRequestCounts[$connectionId]);

        if (empty($this->connections[$uri])) {
            unset($this->connections[$uri], $this->waitForPriorConnection[$uri]);
        }
    }
}
