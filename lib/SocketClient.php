<?php

namespace Amp\Http\Client;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Http\Client\Internal\CallableNetworkClient;
use Amp\Http\Client\Internal\CombinedCancellationToken;
use Amp\Http\Client\Internal\NetworkInterceptorClient;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketPool;
use Amp\TimeoutCancellationToken;
use function Amp\call;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class SocketClient implements Client
{
    /**
     * @param EncryptableSocket $socket
     *
     * @return ConnectionInfo
     * @throws SocketException
     */
    private static function collectConnectionInfo(EncryptableSocket $socket): ConnectionInfo
    {
        if (!$socket instanceof ResourceSocket) {
            return new ConnectionInfo($socket->getLocalAddress(), $socket->getRemoteAddress());
        }

        $stream = $socket->getResource();

        if ($stream === null) {
            throw new SocketException("Socket closed before connection information could be collected");
        }

        $crypto = \stream_get_meta_data($stream)["crypto"] ?? null;

        return new ConnectionInfo(
            $socket->getLocalAddress(),
            $socket->getRemoteAddress(),
            $crypto ? TlsInfo::fromMetaData($crypto, \stream_context_get_options($stream)["ssl"]) : null
        );
    }

    private $socketPool;
    private $driverFactory;
    private $networkInterceptors;

    public function __construct(?SocketPool $socketPool = null, ?Driver\HttpDriverFactory $driverFactory = null)
    {
        $this->socketPool = $socketPool ?? new HttpSocketPool;
        $this->driverFactory = $driverFactory ?? new Driver\DefaultHttpDriverFactory;
        $this->networkInterceptors = [];
    }

    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void
    {
        $this->networkInterceptors[] = $networkInterceptor;
    }

    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
        return call(function () use ($request, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;

            $isHttps = $request->getUri()->getScheme() === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $authority = $request->getUri()->getHost() . ':' . ($request->getUri()->getPort() ?: $defaultPort);
            $socketUri = "tcp://{$authority}";

            $connectContext = new ConnectContext;

            if ($request->getUri()->getScheme() === 'https') {
                $tlsContext = ($connectContext->getTlsContext() ?? new ClientTlsContext($request->getUri()->getHost()))
                    ->withPeerName($request->getUri()->getHost())
                    ->withApplicationLayerProtocols($this->driverFactory->getApplicationLayerProtocols())
                    ->withPeerCapturing();

                $connectContext = $connectContext->withTlsContext($tlsContext);
            }

            try {
                $checkoutCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTcpConnectTimeout()));

                /** @var EncryptableSocket $socket */
                $socket = yield $this->socketPool->checkout($socketUri, $connectContext, $checkoutCancellationToken);
            } catch (Socket\SocketException $e) {
                throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
            } catch (CancelledException $e) {
                // In case of a user cancellation request, throw the expected exception
                $cancellation->throwIfRequested();

                // Otherwise we ran into a timeout of our TimeoutCancellationToken
                throw new TimeoutException(\sprintf("Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms', $authority)); // don't pass $e
            }

            try {
                try {
                    $socket->reference();

                    if ($isHttps) {
                        $tlsState = $socket->getTlsState();
                        if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                            $tlsCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTlsHandshakeTimeout()));
                            yield $socket->setupTls($tlsCancellationToken);
                        } elseif ($tlsState !== EncryptableSocket::TLS_STATE_ENABLED) {
                            throw new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')');
                        }
                    }
                } catch (StreamException $exception) {
                    throw new SocketException(\sprintf("Connection to '%s' closed during TLS handshake", $authority), 0, $exception);
                } catch (CancelledException $e) {
                    // In case of a user cancellation request, throw the expected exception
                    $cancellation->throwIfRequested();

                    // Otherwise we ran into a timeout of our TimeoutCancellationToken
                    throw new TimeoutException(\sprintf("TLS handshake with '%s' @ '%s' timed out, took longer than " . $request->getTlsHandshakeTimeout() . ' ms', $authority, $socket->getRemoteAddress()->toString())); // don't pass $e
                }

                $connectionInfo = self::collectConnectionInfo($socket);

                $driver = $this->driverFactory->selectDriver($connectionInfo, $request);

                $client = new CallableNetworkClient(function () use (
                    $driver,
                    $request,
                    $socket,
                    $connectionInfo,
                    $cancellation
                ): Promise {
                    return $driver->request($socket, $connectionInfo, $request, $cancellation);
                });

                $client = new NetworkInterceptorClient($client, $connectionInfo, ...$this->networkInterceptors);

                /** @var Response $response */
                $response = yield $client->request($request, $cancellation);

                $response->getCompletionPromise()->onResolve(function ($error) use ($socket) {
                    if ($error || $socket->isClosed()) {
                        $this->socketPool->clear($socket);
                        $socket->close();
                    } else {
                        $socket->unreference();
                        $this->socketPool->checkin($socket);
                    }
                });

                return $response;
            } catch (\Throwable $e) {
                $this->socketPool->clear($socket);
                $socket->close();

                throw $e;
            }
        });
    }
}
