<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\CompositeException;
use Amp\DeferredFuture;
use Amp\ForbidSerialization;
use Amp\Future;
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
     * @param int $connectionLimit Maximum number of connections allowed to a single authority.
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

        [$connection, $stream] = $this->getStreamFor($uri, $request, $cancellation);

        $connectionId = \spl_object_id($connection);
        $this->activeRequestCounts[$connectionId] = ($this->activeRequestCounts[$connectionId] ?? 0) + 1;
        unset($this->idleConnections[$connectionId]);

        $poolRef = \WeakReference::create($this);

        return HttpStream::fromStream(
            $stream,
            function (Request $request, Cancellation $cancellation) use (
                $poolRef,
                $connection,
                $stream,
                $uri
            ): Response {
                try {
                    $response = $stream->request($request, $cancellation);
                } catch (\Throwable $e) {
                    $this->onReadyConnection($connection, $uri);
                    throw $e;
                }

                async(static function () use ($poolRef, $response, $connection, $uri): void {
                    try {
                        $response->getTrailers()->await();
                    } finally {
                        $pool = $poolRef->get();
                        if ($pool) {
                            $pool->onReadyConnection($connection, $uri);
                        } elseif ($connection->isIdle()) {
                            $connection->close();
                        }
                    }
                })->ignore();

                return $response;
            },
            static function () use ($poolRef, $connection, $uri): void {
                $pool = $poolRef->get();
                if ($pool) {
                    $pool->onReadyConnection($connection, $uri);
                } elseif ($connection->isIdle()) {
                    $connection->close();
                }
            }
        );
    }

    /**
     * @return array{Connection, Stream}
     */
    private function getStreamFor(string $uri, Request $request, Cancellation $cancellation): array
    {
        $isHttps = $request->getUri()->getScheme() === 'https';

        $connections = $this->connections[$uri] ?? [];

        do {
            foreach ($connections as $connectionFuture) {
                \assert($connectionFuture instanceof Future);

                try {
                    if ($isHttps && ($this->waitForPriorConnection[$uri] ?? true)) {
                        // Wait for first successful connection if using a secure connection (maybe we can use HTTP/2).
                        $connection = $connectionFuture->await();
                    } elseif ($connectionFuture->isComplete()) {
                        $connection = $connectionFuture->await();
                    } else {
                        continue;
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
            $futureFromDeferred = $deferred->getFuture();

            $this->waiting[$uri][\spl_object_id($deferred)] = $deferred;

            if ($this->isAdditionalConnectionAllowed($uri)) {
                break;
            }

            $connection = $futureFromDeferred->await();

            \assert($connection instanceof Connection);

            $stream = $this->getStreamFromConnection($connection, $request);

            if ($stream === null) {
                continue; // Wait for a different connection to become available.
            }

            return [$connection, $stream];
        } while (true);

        $this->totalConnectionAttempts++;

        $connectionFuture = async($this->connectionFactory->create(...), $request, $cancellation);

        $futureId = \spl_object_id($connectionFuture);
        $this->connections[$uri] ??= new \ArrayObject();
        $this->connections[$uri][$futureId] = $connectionFuture;

        EventLoop::queue(function () use (
            $connectionFuture,
            $uri,
            $futureId,
            $isHttps
        ): void {
            try {
                /** @var Connection $connection */
                $connection = $connectionFuture->await();
            } catch (\Throwable) {
                $this->dropConnection($uri, null, $futureId);
                return;
            }

            $connectionId = \spl_object_id($connection);
            $this->openConnectionCount++;

            if ($isHttps) {
                $this->waitForPriorConnection[$uri] = \in_array('2', $connection->getProtocolVersions(), true);
            }

            $poolRef = \WeakReference::create($this);
            $connection->onClose(static function () use ($poolRef, $uri, $connectionId, $futureId): void {
                $pool = $poolRef->get();
                if ($pool) {
                    $pool->openConnectionCount--;
                    $pool->dropConnection($uri, $connectionId, $futureId);
                }
            });
        });

        try {
            // Await both new connection future and deferred to reuse an existing connection.
            $connection = Future\awaitFirst([$connectionFuture, $futureFromDeferred]);
        } catch (CompositeException $exception) {
            [$exception] = $exception->getReasons(); // The first reason is why the connection failed.
            throw $exception;
        }

        $this->removeWaiting($uri, \spl_object_id($deferred)); // DeferredFuture no longer needed for this request.

        \assert($connection instanceof Connection);

        $stream = $this->getStreamFromConnection($connection, $request);

        if ($stream === null) {
            // Potentially reused connection did not have an available stream for the given request.
            $connection = $connectionFuture->await(); // Wait for new connection request instead.

            $stream = $this->getStreamFromConnection($connection, $request);

            if ($stream === null) {
                // Other requests used the new connection first, so we need to go around again.
                return $this->getStreamFor($uri, $request, $cancellation);
            }
        }

        return [$connection, $stream];
    }

    private function getStreamFromConnection(Connection $connection, Request $request): ?Stream
    {
        if ($connection->isClosed()) {
            return null; // Connection closed during iteration over available connections.
        }

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

    private function dropConnection(string $uri, ?int $connectionId, int $futureId): void
    {
        unset($this->connections[$uri][$futureId]);
        if ($connectionId !== null) {
            unset($this->activeRequestCounts[$connectionId], $this->idleConnections[$connectionId]);
        }

        if (\count($this->connections[$uri]) === 0) {
            unset($this->connections[$uri], $this->waitForPriorConnection[$uri]);
        }
    }
}
