<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Http\Client\Internal\CombinedCancellationToken;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\TimeoutCancellationToken;
use function Amp\call;

final class DefaultConnectionPool implements ConnectionPool
{
    /** @var Connector */
    private $connector;

    /** @var \SplObjectStorage[] */
    private $connections = [];

    public function __construct(?Connector $connector = null)
    {
        $this->connector = $connector ?? Socket\connector();
    }

    public function getConnection(Request $request, CancellationToken $cancellation): Promise
    {
        return call(function () use ($request, $cancellation) {
            $uri = $request->getUri();
            $isHttps = $uri->getScheme() === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $authority = $uri->getHost() . ':' . ($uri->getPort() ?: $defaultPort);
            $key = $uri->getScheme() . '://' . $authority;

            if (isset($this->connections[$key])) {
                foreach ($this->connections[$key] as $connection) {
                    $connection = yield $connection;
                    \assert($connection instanceof Connection);

                    if (!$connection->isBusy()) {
                        return $connection;
                    }
                }
            } else {
                $this->connections[$key] = new \SplObjectStorage;
            }

            \assert($this->connections[$key] instanceof \SplObjectStorage);

            $promise = call(function () use (&$promise, $request, $isHttps, $authority, $cancellation, $key) {
                $connectContext = new ConnectContext;

                if ($isHttps) {
                    $tlsContext = ($connectContext->getTlsContext() ?? new ClientTlsContext($request->getUri()->getHost()))
                        ->withApplicationLayerProtocols(['http/1.1'])
                        ->withPeerCapturing();

                    if (\in_array('2.0', $request->getProtocolVersions(), true)) {
                        $tlsContext = $tlsContext->withApplicationLayerProtocols(['h2', 'http/1.1']);
                    }

                    $connectContext = $connectContext->withTlsContext($tlsContext);
                }

                try {
                    $checkoutCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTcpConnectTimeout()));

                    /** @var EncryptableSocket $socket */
                    $socket = yield $this->connector->connect('tcp://' . $authority, $connectContext, $checkoutCancellationToken);
                } catch (Socket\ConnectException $e) {
                    throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
                } catch (CancelledException $e) {
                    // In case of a user cancellation request, throw the expected exception
                    $cancellation->throwIfRequested();

                    // Otherwise we ran into a timeout of our TimeoutCancellationToken
                    throw new TimeoutException(\sprintf("Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms', $authority)); // don't pass $e
                }

                if ($isHttps) {
                    try {
                        $tlsState = $socket->getTlsState();
                        if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                            $tlsCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTlsHandshakeTimeout()));
                            yield $socket->setupTls($tlsCancellationToken);
                        } elseif ($tlsState !== EncryptableSocket::TLS_STATE_ENABLED) {
                            throw new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')');
                        }
                    } catch (StreamException $exception) {
                        throw new SocketException(\sprintf("Connection to '%s' closed during TLS handshake", $authority), 0, $exception);
                    } catch (CancelledException $e) {
                        // In case of a user cancellation request, throw the expected exception
                        $cancellation->throwIfRequested();

                        // Otherwise we ran into a timeout of our TimeoutCancellationToken
                        throw new TimeoutException(\sprintf("TLS handshake with '%s' @ '%s' timed out, took longer than " . $request->getTlsHandshakeTimeout() . ' ms', $authority, $socket->getRemoteAddress()->toString())); // don't pass $e
                    }
                }

                if ($isHttps && $socket->getTlsInfo()->getApplicationLayerProtocol() === 'h2') {
                    $connection = new Http2Connection($socket);
                } else {
                    $connection = new Http1Connection($socket);
                }

                \assert($promise instanceof Promise);

                $connections = &$this->connections;
                $connection->onClose(static function () use (&$connections, $key, $promise) {
                    $connections[$key]->detach($promise);

                    if (!$connections[$key]->count()) {
                        unset($connections[$key]);
                    }
                });

                return $connection;
            });

            $this->connections[$key]->attach($promise);

            $promise->onResolve(function (?\Throwable $exception) use ($key, $promise): void {
                if (!$exception) {
                    return;
                }

                // Connection failed, remove from list of connections.
                $this->connections[$key]->detach($promise);

                if (!$this->connections[$key]->count()) {
                    unset($this->connections[$key]);
                }
            });

            return $promise;
        });
    }
}
