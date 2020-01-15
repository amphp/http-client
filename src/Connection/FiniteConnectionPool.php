<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\InvalidRequestException;
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
    private $maxConnections;

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
     * Create a connection pool that limits the number of connections per authority to $maxConnections.
     *
     * @param int                    $maxConnections
     * @param ConnectionFactory|null $connectionFactory
     *
     * @return self
     */
    public static function byAuthority(int $maxConnections, ?ConnectionFactory $connectionFactory = null): self
    {
        return new self($maxConnections, function (Request $request): string {
            $uri = $request->getUri();
            $scheme = $uri->getScheme();

            $isHttps = $scheme === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $host = $uri->getHost();
            $port = $uri->getPort() ?? $defaultPort;

            $authority = $host . ':' . $port;
            return $scheme . '://' . $authority;
        }, $connectionFactory);
    }

    private function __construct(int $maxConnections, callable $requestToKeyMapper, ?ConnectionFactory $connectionFactory = null)
    {
        if ($maxConnections < 1) {
            throw new \Error('The number of max connections per authority must be greater than 0');
        }

        $this->maxConnections = $maxConnections;
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

    private function fetchStream(string $uri, Request $request, CancellationToken $cancellation): \Generator
    {
        $this->totalStreamRequests++;

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

                if (!\array_intersect($request->getProtocolVersions(), $connection->getProtocolVersions())) {
                    continue; // Connection does not support any of the requested protocol versions.
                }

                $stream = yield $connection->getStream($request);

                if ($stream === null) {
                    continue; // No stream available for the given request.
                }

                return $stream;
            }

            if (!isset($this->connections[$uri]) || \count($this->connections[$uri]) < $this->maxConnections) {
                break; // Create a new connection.
            }

            $this->waiting[$uri][] = $deferred = new Deferred;
            yield $deferred->promise();
        } while (true);

        $this->totalConnectionAttempts++;

        $connectionPromise = $this->connectionFactory->create($request, $cancellation);

        $hash = \spl_object_hash($connectionPromise);
        $this->connections[$uri] = $this->connections[$uri] ?? new \ArrayObject;
        $this->connections[$uri][$hash] = $connectionPromise;

        try {
            $connection = yield $connectionPromise;
            $this->openConnectionCount++;

            \assert($connection instanceof Connection);
        } catch (\Throwable $exception) {
            $this->dropConnection($uri, $hash);

            throw $exception;
        }

        if ($isHttps) {
            $this->waitForPriorConnection[$uri] = \in_array('2', $connection->getProtocolVersions());
        }

        $connection->onClose(function () use ($uri, $hash): void {
            $this->openConnectionCount--;
            $this->dropConnection($uri, $hash);
        });

        $stream = yield $connection->getStream($request);

        \assert($stream instanceof Stream); // New connection must always resolve with a Stream instance.

        return $stream;
    }

    private function release(string $uri): void
    {
        if (!isset($this->waiting[$uri])) {
            return;
        }

        $deferred = \array_shift($this->waiting[$uri]);

        if (empty($this->waiting[$uri])) {
            unset($this->waiting[$uri]);
        }

        $deferred->resolve();
    }

    private function dropConnection(string $uri, string $connectionHash): void
    {
        unset($this->connections[$uri][$connectionHash]);

        if (empty($this->connections[$uri])) {
            unset($this->connections[$uri], $this->waitForPriorConnection[$uri]);
        }

        $this->release($uri);
    }
}
