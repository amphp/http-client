<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\CombinedCancellationToken;
use Amp\Coroutine;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use function Amp\call;

final class UnlimitedConnectionPool implements ConnectionPool
{
    use ForbidSerialization;

    private const PROTOCOL_VERSIONS = ['1.0', '1.1', '2'];

    /** @var Connector */
    private $connector;

    /** @var ConnectContext */
    private $connectContext;

    /** @var Promise[][] */
    private $connections = [];

    /** @var int */
    private $timeoutGracePeriod = 2000;

    /** @var int */
    private $totalConnectionAttempts = 0;

    /** @var int */
    private $totalStreamRequests = 0;

    /** @var int */
    private $openConnectionCount = 0;

    public function __construct(?Connector $connector = null, ?ConnectContext $connectContext = null)
    {
        $this->connector = $connector ?? Socket\connector();
        $this->connectContext = $connectContext ?? new ConnectContext;
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
            $scheme = \strtolower($uri->getScheme());
            $isHttps = $scheme === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $host = \strtolower($uri->getHost());
            $port = $uri->getPort() ?? $defaultPort;

            if ($host === '') {
                throw new InvalidRequestException($request, 'A host must be provided in the request URI: ' . $uri);
            }

            $authority = $host . ':' . $port;
            $key = $scheme . '://' . $authority;

            if (!\array_intersect($request->getProtocolVersions(), self::PROTOCOL_VERSIONS)) {
                throw new InvalidRequestException(
                    $request,
                    'None of the requested protocol versions are supported; Supported versions: '
                    . \implode(', ', self::PROTOCOL_VERSIONS)
                );
            }

            if (!$isHttps && !\array_intersect($request->getProtocolVersions(), ['1.0', '1.1'])) {
                throw new InvalidRequestException(
                    $request,
                    'HTTP/1.x forbidden, but a secure connection (HTTPS) is required for HTTP/2'
                );
            }

            $connections = $this->connections[$key] ?? [];

            foreach ($connections as $promise) {
                \assert($promise instanceof Promise);

                try {
                    if ($isHttps && \count($connections) === 1) {
                        // Wait for first successful connection if using a secure connection (maybe we can use HTTP/2).
                        $connection = yield $promise;
                    } else {
                        $connection = yield Promise\first([$promise, new Success]);
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

            foreach ($request->getEventListeners() as $eventListener) {
                yield $eventListener->startConnectionCreation($request);
            }

            $promise = new Coroutine($this->createConnection($request, $cancellation, $authority, $isHttps));

            $hash = \spl_object_hash($promise);
            $this->connections[$key] = $this->connections[$key] ?? [];
            $this->connections[$key][$hash] = $promise;

            try {
                $connection = yield $promise;
                $this->openConnectionCount++;
                \assert($connection instanceof Connection);
            } catch (\Throwable $exception) {
                // Connection failed, remove from list of connections.
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

    /**
     * @param int $timeout Number of milliseconds before the estimated connection timeout that a non-idempotent
     *                     request should will not be sent on an existing HTTP/1.x connection, instead opening a
     *                     new connection for the request. Default is 2000 ms.
     *
     * @return self
     */
    public function withTimeoutGracePeriod(int $timeout): self
    {
        $pool = clone $this;
        $pool->timeoutGracePeriod = $timeout;
        return $pool;
    }

    private function createConnection(
        Request $request,
        CancellationToken $cancellation,
        string $authority,
        bool $isHttps
    ): \Generator {
        $this->totalConnectionAttempts++;

        $connectContext = $this->connectContext;

        if ($isHttps) {
            if (\in_array('2', $request->getProtocolVersions(), true)) {
                $protocols = ['h2', 'http/1.1'];
            } else {
                $protocols = ['http/1.1'];
            }

            $tlsContext = ($connectContext->getTlsContext() ?? new ClientTlsContext($request->getUri()->getHost()))
                ->withApplicationLayerProtocols($protocols)
                ->withPeerCapturing();

            if ($tlsContext->getPeerName() === '') {
                $tlsContext = $tlsContext->withPeerName($request->getUri()->getHost());
            }

            $connectContext = $connectContext->withTlsContext($tlsContext);
        }

        try {
            /** @var EncryptableSocket $socket */
            $socket = yield $this->connector->connect(
                'tcp://' . $authority,
                $connectContext->withConnectTimeout($request->getTcpConnectTimeout()),
                $cancellation
            );
        } catch (Socket\ConnectException $e) {
            throw new UnprocessedRequestException(
                new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e)
            );
        } catch (CancelledException $e) {
            // In case of a user cancellation request, throw the expected exception
            $cancellation->throwIfRequested();

            // Otherwise we ran into a timeout of our TimeoutCancellationToken
            throw new TimeoutException(\sprintf(
                "Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms',
                $authority
            )); // don't pass $e
        }

        if (!$isHttps) {
            foreach ($request->getEventListeners() as $eventListener) {
                yield $eventListener->completeConnectionCreation($request);
            }

            return new Http1Connection($socket, $this->timeoutGracePeriod);
        }

        try {
            $tlsState = $socket->getTlsState();
            if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->startTlsNegotiation($request);
                }

                $tlsCancellationToken = new CombinedCancellationToken(
                    $cancellation,
                    new TimeoutCancellationToken($request->getTlsHandshakeTimeout())
                );

                yield $socket->setupTls($tlsCancellationToken);

                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->completeTlsNegotiation($request);
                }
            } elseif ($tlsState !== EncryptableSocket::TLS_STATE_ENABLED) {
                $socket->close();
                throw new UnprocessedRequestException(
                    new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')')
                );
            }
        } catch (StreamException $exception) {
            $socket->close();
            throw new UnprocessedRequestException(new SocketException(\sprintf(
                "Connection to '%s' closed during TLS handshake",
                $authority
            ), 0, $exception));
        } catch (CancelledException $e) {
            $socket->close();

            // In case of a user cancellation request, throw the expected exception
            $cancellation->throwIfRequested();

            // Otherwise we ran into a timeout of our TimeoutCancellationToken
            throw new TimeoutException(\sprintf(
                "TLS handshake with '%s' @ '%s' timed out, took longer than " . $request->getTlsHandshakeTimeout() . ' ms',
                $authority,
                $socket->getRemoteAddress()->toString()
            )); // don't pass $e
        }

        $tlsInfo = $socket->getTlsInfo();
        if ($tlsInfo === null) {
            throw new UnprocessedRequestException(
                new SocketException('Socket disconnected immediately after enabling TLS')
            );
        }

        if ($tlsInfo->getApplicationLayerProtocol() === 'h2') {
            $connection = new Http2Connection($socket);
            yield $connection->initialize();

            foreach ($request->getEventListeners() as $eventListener) {
                yield $eventListener->completeConnectionCreation($request);
            }

            return $connection;
        }

        if (!\array_intersect($request->getProtocolVersions(), ['1.0', '1.1'])) {
            $socket->close();
            throw new InvalidRequestException(
                $request,
                'Downgrade to HTTP/1.x forbidden, but server does not support HTTP/2'
            );
        }

        foreach ($request->getEventListeners() as $eventListener) {
            yield $eventListener->completeConnectionCreation($request);
        }

        return new Http1Connection($socket, $this->timeoutGracePeriod);
    }

    private function dropConnection(string $uri, string $connectionHash): void
    {
        unset($this->connections[$uri][$connectionHash]);

        if (empty($this->connections[$uri])) {
            unset($this->connections[$uri]);
        }
    }
}
