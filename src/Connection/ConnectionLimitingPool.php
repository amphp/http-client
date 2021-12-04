<?php

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\CompositeException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Revolt\EventLoop;
use function Amp\async;

final class ConnectionLimitingPool implements ConnectionPool
{
    use ForbidSerialization;

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

    private int $connectionLimit;

    private ConnectionFactory $connectionFactory;

    /** @var array<string, \ArrayObject<int, Future<Connection>>> */
    private array $connections = [];

    /** @var Connection[] */
    private array $idleConnections = [];

    /** @var int[] */
    private array $activeRequestCounts = [];

    /** @var DeferredFuture[][] */
    private array $waiting = [];

    /** @var bool[] */
    private array $waitForPriorConnection = [];

    private int $totalConnectionAttempts = 0;

    private int $totalStreamRequests = 0;

    private int $openConnectionCount = 0;

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

    public function getStream(Request $request, Cancellation $cancellation): Stream
    {
        $this->totalStreamRequests++;

        $uri = self::formatUri($request);

        /**
         * @var Stream $stream
         * @psalm-suppress all
         */
        [$connection, $stream] = $this->getStreamFor($uri, $request, $cancellation);

        $connectionId = \spl_object_id($connection);
        $this->activeRequestCounts[$connectionId] = ($this->activeRequestCounts[$connectionId] ?? 0) + 1;
        unset($this->idleConnections[$connectionId]);

        return HttpStream::fromStream(
            $stream,
            function (Request $request, Cancellation $cancellationToken) use (
                $connection,
                $stream,
                $uri
            ): Response {
                try {
                    $response = $stream->request($request, $cancellationToken);
                } catch (\Throwable $e) {
                    $this->onReadyConnection($connection, $uri);
                    throw $e;
                }

                async(function () use ($response, $connection, $uri): void {
                    try {
                        $response->getTrailers()->await();
                    } finally {
                        $this->onReadyConnection($connection, $uri);
                    }
                })->ignore();

                return $response;
            },
            function () use ($connection, $uri): void {
                $this->onReadyConnection($connection, $uri);
            }
        );
    }

    private function getStreamFor(string $uri, Request $request, Cancellation $cancellation): array
    {
        $isHttps = $request->getUri()->getScheme() === 'https';

        $connections = $this->connections[$uri] ?? new \ArrayObject;

        do {
            foreach ($connections as $connectionFuture) {
                \assert($connectionFuture instanceof Future);

                try {
                    if ($isHttps && ($this->waitForPriorConnection[$uri] ?? true)) {
                        // Wait for first successful connection if using a secure connection (maybe we can use HTTP/2).
                        $connection = $connectionFuture->await();
                    } else {
                        $connection = Future\race([$connectionFuture, Future::complete(null)]);
                        if ($connection === null) {
                            continue;
                        }
                    }
                } catch (\Exception $exception) {
                    continue; // Ignore cancellations and errors of other requests.
                }

                \assert($connection instanceof Connection);

                $stream = $this->getStreamFromConnection($connection, $request);

                if ($stream === null) {
                    if (!$this->isAdditionalConnectionAllowed($uri) && $this->isConnectionIdle($connection)) {
                        $connection->close();
                        break;
                    }

                    continue; // No stream available for the given request.
                }

                return [$connection, $stream];
            }

            $deferred = new DeferredFuture;
            $deferredFuture = $deferred->getFuture();

            $this->waiting[$uri][\spl_object_id($deferred)] = $deferred;

            if ($this->isAdditionalConnectionAllowed($uri)) {
                break;
            }

            $connection = $deferredFuture->await();

            \assert($connection instanceof Connection);

            $stream = $this->getStreamFromConnection($connection, $request);

            if ($stream === null) {
                continue; // Wait for a different connection to become available.
            }

            return [$connection, $stream];
        } while (true);

        $this->totalConnectionAttempts++;

        $connectionFuture = async(fn () => $this->connectionFactory->create($request, $cancellation));

        $promiseId = \spl_object_id($connectionFuture);
        $this->connections[$uri] = $this->connections[$uri] ?? new \ArrayObject;
        $this->connections[$uri][$promiseId] = $connectionFuture;

        EventLoop::queue(function () use (
            &$deferred,
            $connectionFuture,
            $uri,
            $promiseId,
            $isHttps
        ): void {
            try {
                /** @var Connection $connection */
                $connection = $connectionFuture->await();
            } catch (\Throwable $exception) {
                $this->dropConnection($uri, null, $promiseId);
                if ($deferred !== null) {
                    $deferred->error($exception); // Fail DeferredFuture so Promise\first() below fails.
                }
                return;
            }

            \assert($connection !== null);

            $connectionId = \spl_object_id($connection);
            $this->openConnectionCount++;

            if ($isHttps) {
                $this->waitForPriorConnection[$uri] = \in_array('2', $connection->getProtocolVersions(), true);
            }

            $connection->onClose(function () use ($uri, $connectionId, $promiseId): void {
                $this->openConnectionCount--;
                $this->dropConnection($uri, $connectionId, $promiseId);
            });
        });

        try {
            $connection = Future\any([$connectionFuture, $deferredFuture]);
        } catch (CompositeException $exception) {
            [$exception] = $exception->getReasons(); // The first reason is why the connection failed.
            throw $exception;
        }

        $this->removeWaiting($uri, \spl_object_id($deferred)); // DeferredFuture no longer needed for this request.
        $deferred = null; // Null reference so connection promise handler does not double-resolve the DeferredFuture.

        \assert($connection instanceof Connection);

        $stream = $this->getStreamFromConnection($connection, $request);

        if ($stream === null) {
            // Reused connection did not have an available stream for the given request.
            $connection = $connectionFuture->await(); // Wait for new connection request instead.

            $stream = $this->getStreamFromConnection($connection, $request);

            if ($stream === null) {
                // Other requests used the new connection first, so we need to go around again.
                // Using new Coroutine avoids a bug on PHP < 7.4, see #265
                return $this->getStreamFor($uri, $request, $cancellation);
            }
        }

        return [$connection, $stream];
    }

    private function getStreamFromConnection(Connection $connection, Request $request): ?Stream
    {
        if (!\array_intersect($request->getProtocolVersions(), $connection->getProtocolVersions())) {
            return null; // Connection does not support any of the requested protocol versions.
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

            if ($this->activeRequestCounts[$connectionId] === 0) {
                while (\count($this->idleConnections) > 64) { // not customizable for now
                    $idleConnection = \reset($this->idleConnections);
                    $key = \key($this->idleConnections);
                    unset($this->idleConnections[$key]);
                    $idleConnection->close();
                }

                $this->idleConnections[$connectionId] = $connection;
            }
        }

        if (empty($this->waiting[$uri])) {
            return;
        }

        /** @var DeferredFuture $deferred */
        $deferred = \reset($this->waiting[$uri]);
        $this->removeWaiting($uri, \spl_object_id($deferred));
        $deferred->complete($connection);
    }

    private function isConnectionIdle(Connection $connection): bool
    {
        $connectionId = \spl_object_id($connection);

        \assert(
            !isset($this->activeRequestCounts[$connectionId])
            || $this->activeRequestCounts[$connectionId] >= 0
        );

        return ($this->activeRequestCounts[$connectionId] ?? 0) === 0;
    }

    private function removeWaiting(string $uri, int $deferredId): void
    {
        unset($this->waiting[$uri][$deferredId]);
        if (empty($this->waiting[$uri])) {
            unset($this->waiting[$uri]);
        }
    }

    private function dropConnection(string $uri, ?int $connectionId, int $promiseId): void
    {
        unset($this->connections[$uri][$promiseId]);
        if ($connectionId !== null) {
            unset($this->activeRequestCounts[$connectionId], $this->idleConnections[$connectionId]);
        }

        if ($this->connections[$uri]->count() === 0) {
            unset($this->connections[$uri], $this->waitForPriorConnection[$uri]);
        }
    }
}
