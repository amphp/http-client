<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class UnlimitedConnectionPool implements ConnectionPool
{
    use ForbidSerialization;

    /** @var ConnectionFactory */
    private $connectionFactory;

    /** @var Promise[][] */
    private $connections = [];

    /** @var bool[] */
    private $waitForPriorConnection = [];

    /** @var int */
    private $totalConnectionAttempts = 0;

    /** @var int */
    private $totalStreamRequests = 0;

    /** @var int */
    private $openConnectionCount = 0;

    public function __construct(?ConnectionFactory $connectionFactory = null)
    {
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

            $uri = $request->getUri();
            $scheme = $uri->getScheme();

            $isHttps = $scheme === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $host = $uri->getHost();
            $port = $uri->getPort() ?? $defaultPort;

            if ($host === '') {
                throw new InvalidRequestException($request, 'A host must be provided in the request URI: ' . $uri);
            }

            $authority = $host . ':' . $port;
            $uri = $scheme . '://' . $authority;

            $connections = $this->connections[$uri] ?? new \ArrayObject;

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

            $this->totalConnectionAttempts++;

            $connectionPromise = $this->connectionFactory->create($request, $cancellation);

            $id = \spl_object_id($connectionPromise);
            $this->connections[$uri] = $this->connections[$uri] ?? new \ArrayObject;
            $this->connections[$uri][$id] = $connectionPromise;

            try {
                $connection = yield $connectionPromise;
                $this->openConnectionCount++;

                \assert($connection instanceof Connection);
            } catch (\Throwable $exception) {
                $this->dropConnection($uri, $id);

                throw $exception;
            }

            if ($isHttps) {
                $this->waitForPriorConnection[$uri] = \in_array('2', $connection->getProtocolVersions(), true);
            }

            $connection->onClose(function () use ($uri, $id): void {
                $this->openConnectionCount--;
                $this->dropConnection($uri, $id);
            });

            $stream = yield $connection->getStream($request);

            \assert($stream instanceof Stream); // New connection must always resolve with a Stream instance.

            return $stream;
        });
    }

    private function dropConnection(string $uri, int $connectionId): void
    {
        unset($this->connections[$uri][$connectionId]);

        if (empty($this->connections[$uri])) {
            unset($this->connections[$uri], $this->waitForPriorConnection[$uri]);
        }
    }
}
