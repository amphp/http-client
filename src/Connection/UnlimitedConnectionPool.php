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
            $key = $scheme . '://' . $authority;

            $connections = $this->connections[$key] ?? new \ArrayObject;

            foreach ($connections as $connectionPromise) {
                \assert($connectionPromise instanceof Promise);

                try {
                    if ($isHttps) {
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

            $connection->onClose(function () use ($key, $hash): void {
                $this->openConnectionCount--;
                $this->dropConnection($key, $hash);
            });

            $stream = yield $connection->getStream($request);

            \assert($stream instanceof Stream); // New connection must always resolve with a Stream instance.

            return $stream;
        });
    }

    private function dropConnection(string $uri, string $connectionHash): void
    {
        unset($this->connections[$uri][$connectionHash]);

        if (empty($this->connections[$uri])) {
            unset($this->connections[$uri]);
        }
    }
}
