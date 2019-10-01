<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\CombinedCancellationToken;
use Amp\Http\Client\HttpException;
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

final class DefaultConnectionPool implements ConnectionPool
{
    private const PROTOCOL_VERSIONS = ['1.0', '1.1', '2'];

    /** @var Connector */
    private $connector;

    /** @var ConnectContext */
    private $connectContext;

    /** @var \SplObjectStorage[] */
    private $connections = [];

    public function __construct(?Connector $connector = null, ?ConnectContext $connectContext = null)
    {
        $this->connector = $connector ?? Socket\connector();
        $this->connectContext = $connectContext ?? new ConnectContext;
    }

    public function getStream(Request $request, CancellationToken $cancellation): Promise
    {
        return call(function () use ($request, $cancellation) {
            $uri = $request->getUri();
            $scheme = \strtolower($uri->getScheme());
            $isHttps = $scheme === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $host = \strtolower($uri->getHost());
            $port = $uri->getPort() ?? $defaultPort;

            if ($host === '') {
                throw new HttpException('A host must be provided in the request URI: ' . $uri);
            }

            $authority = $host . ':' . $port;
            $key = $scheme . '://' . $authority;

            if (!\array_intersect($request->getProtocolVersions(), self::PROTOCOL_VERSIONS)) {
                throw new HttpException('None of the requested protocol versions are supported; Supported versions: '
                    . \implode(', ', self::PROTOCOL_VERSIONS));
            }

            $connections = $this->connections[$key] ?? new \SplObjectStorage;

            foreach ($connections as $connection) {
                \assert($connection instanceof Promise);

                try {
                    if ($isHttps && $connections->count() === 1) {
                        // Wait for first successful connection if using a secure connection (maybe we can use HTTP/2)
                        $connection = yield $connection;
                    } else {
                        $connection = yield Promise\first([$connection, new Success]);
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

                $remainingTime = $connection->getRemainingTime();
                $highCloseRisk = $remainingTime === null || $remainingTime < 2000;

                if ($highCloseRisk && !isRetryAllowed($request)) {
                    continue;
                }

                if (!$connection->isBusy()) {
                    return $connection->getStream($request);
                }
            }

            $promise = call(function () use (&$promise, $request, $isHttps, $authority, $cancellation, $key) {
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
                    throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
                } catch (CancelledException $e) {
                    // In case of a user cancellation request, throw the expected exception
                    $cancellation->throwIfRequested();

                    // Otherwise we ran into a timeout of our TimeoutCancellationToken
                    throw new TimeoutException(\sprintf(
                        "Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms',
                        $authority
                    )); // don't pass $e
                }

                if ($isHttps) {
                    try {
                        $tlsState = $socket->getTlsState();
                        if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                            $tlsCancellationToken = new CombinedCancellationToken(
                                $cancellation,
                                new TimeoutCancellationToken($request->getTlsHandshakeTimeout())
                            );
                            yield $socket->setupTls($tlsCancellationToken);
                        } elseif ($tlsState !== EncryptableSocket::TLS_STATE_ENABLED) {
                            $socket->close();
                            throw new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')');
                        }
                    } catch (StreamException $exception) {
                        $socket->close();
                        throw new SocketException(\sprintf(
                            "Connection to '%s' closed during TLS handshake",
                            $authority
                        ), 0, $exception);
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
                    \assert($tlsInfo !== null);

                    if ($tlsInfo->getApplicationLayerProtocol() === 'h2') {
                        $connection = new Http2Connection($socket);
                        yield $connection->initialize();
                    } else {
                        if (!\array_intersect($request->getProtocolVersions(), ['1.0', '1.1'])) {
                            $socket->close();
                            throw new HttpException('Downgrade to HTTP/1.x forbidden, but server does not support HTTP/2');
                        }

                        $connection = new Http1Connection($socket);
                    }
                } else {
                    $connection = new Http1Connection($socket);
                }

                \assert($promise instanceof Promise);

                $connection->onClose(function () use ($key, $promise) {
                    $this->connections[$key]->detach($promise);

                    if (!$this->connections[$key]->count()) {
                        unset($this->connections[$key]);
                    }
                });

                return $connection;
            });

            $this->connections[$key] = $this->connections[$key] ?? new \SplObjectStorage;
            $this->connections[$key]->attach($promise);

            try {
                $connection = yield $promise;
                \assert($connection instanceof Connection);
            } catch (\Throwable $exception) {
                // Connection failed, remove from list of connections.
                $this->connections[$key]->detach($promise);

                if (!$this->connections[$key]->count()) {
                    unset($this->connections[$key]);
                }

                throw $exception;
            }

            return $connection->getStream($request);
        });
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }
}
