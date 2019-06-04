<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Http\Client\Client;
use Amp\Http\Client\ConnectionInfo;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketClient;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TlsInfo;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\ResourceSocket;
use Amp\Socket\SocketPool;
use Amp\TimeoutCancellationToken;
use function Amp\call;

class PoolClient implements Client
{
    private $socketPool;
    private $networkInterceptors;

    public function __construct(SocketPool $socketPool, NetworkInterceptor ...$networkInterceptors)
    {
        $this->socketPool = $socketPool;
        $this->networkInterceptors = $networkInterceptors;
    }

    /** @inheritDoc */
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
                    ->withPeerCapturing();

                $connectContext = $connectContext->withTlsContext($tlsContext);
            }

            try {
                $checkoutCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken(10000));

                /** @var EncryptableSocket $socket */
                $socket = yield $this->socketPool->checkout($socketUri, $connectContext, $checkoutCancellationToken);
            } catch (Socket\SocketException $e) {
                throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
            } catch (CancelledException $e) {
                // In case of a user cancellation request, throw the expected exception
                $cancellation->throwIfRequested();

                // Otherwise we ran into a timeout of our TimeoutCancellationToken
                throw new SocketException(\sprintf("Connection to '%s' timed out", $authority)); // don't pass $e
            }

            try {
                $socket->reference();

                if ($isHttps) {
                    $tlsState = $socket->getTlsState();
                    if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                        yield $socket->setupTls();
                    } elseif ($tlsState !== EncryptableSocket::TLS_STATE_ENABLED) {
                        throw new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')');
                    }
                }
            } catch (StreamException $exception) {
                $this->socketPool->clear($socket);
                $socket->close();

                throw new SocketException(\sprintf("Connection to '%s' closed during TLS handshake", $authority), 0, $exception);
            }

            try {
                $connectionInfo = $this->collectConnectionInfo($socket);
                $socketClient = new SocketClient($socket, $connectionInfo);
                $client = new NetworkInterceptorClient($socketClient, $connectionInfo, ...$this->networkInterceptors);

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

    /**
     * @param EncryptableSocket $socket
     *
     * @return ConnectionInfo
     * @throws SocketException
     */
    private function collectConnectionInfo(EncryptableSocket $socket): ConnectionInfo
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
}
