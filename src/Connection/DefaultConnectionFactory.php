<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\CombinedCancellationToken;
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
use Amp\TimeoutCancellationToken;
use function Amp\call;
use function Amp\Socket\connector;

final class DefaultConnectionFactory implements ConnectionFactory
{
    /** @var Connector|null */
    private $connector;

    /** @var ConnectContext|null */
    private $connectContext;

    public function __construct(?Connector $connector = null, ?ConnectContext $connectContext = null)
    {
        $this->connector = $connector;
        $this->connectContext = $connectContext;
    }

    public function create(
        Request $request,
        CancellationToken $cancellationToken
    ): Promise {
        return call(function () use ($request, $cancellationToken) {
            foreach ($request->getEventListeners() as $eventListener) {
                yield $eventListener->startConnectionCreation($request);
            }

            $connector = $this->connector ?? connector();
            $connectContext = $this->connectContext ?? new ConnectContext;

            $uri = $request->getUri();
            $scheme = $uri->getScheme();

            if (!\in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidRequestException($request, 'Invalid scheme provided in the request URI: ' . $uri);
            }

            $isHttps = $scheme === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $host = $uri->getHost();
            $port = $uri->getPort() ?? $defaultPort;

            if ($host === '') {
                throw new InvalidRequestException($request, 'A host must be provided in the request URI: ' . $uri);
            }

            $authority = $host . ':' . $port;
            $protocolVersions = $request->getProtocolVersions();

            $isConnect = $request->getMethod() === 'CONNECT';

            if ($isHttps) {
                $protocols = [];

                if (!$isConnect && \in_array('2', $protocolVersions, true)) {
                    $protocols[] = 'h2';
                }

                if (\in_array('1.1', $protocolVersions, true) || \in_array('1.0', $protocolVersions, true)) {
                    $protocols[] = 'http/1.1';
                }

                if (!$protocols) {
                    throw new InvalidRequestException(
                        $request,
                        \sprintf(
                            "None of the requested protocol versions (%s) are supported by %s (HTTP/2 is only supported on HTTPS)",
                            \implode(', ', $protocolVersions),
                            self::class
                        )
                    );
                }

                $tlsContext = ($connectContext->getTlsContext() ?? new ClientTlsContext(''))
                    ->withPeerCapturing();

                // If we only have HTTP/1.1 available, don't set application layer protocols.
                // There are misbehaving sites like n11.com, see https://github.com/amphp/http-client/issues/255
                if ($protocols !== ['http/1.1'] && Socket\hasTlsAlpnSupport()) {
                    $tlsContext = $tlsContext->withApplicationLayerProtocols($protocols);
                }

                if ($tlsContext->getPeerName() === '') {
                    $tlsContext = $tlsContext->withPeerName($host);
                }

                $connectContext = $connectContext->withTlsContext($tlsContext);
            }

            try {
                /** @var EncryptableSocket $socket */
                $socket = yield $connector->connect(
                    'tcp://' . $authority,
                    $connectContext->withConnectTimeout($request->getTcpConnectTimeout()),
                    $cancellationToken
                );
            } catch (Socket\ConnectException $e) {
                throw new UnprocessedRequestException(
                    new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e)
                );
            } catch (CancelledException $e) {
                // In case of a user cancellation request, throw the expected exception
                $cancellationToken->throwIfRequested();

                // Otherwise we ran into a timeout of our TimeoutCancellationToken
                throw new UnprocessedRequestException(new TimeoutException(\sprintf(
                    "Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms',
                    $authority
                ))); // don't pass $e
            }

            if ($isHttps) {
                try {
                    $tlsState = $socket->getTlsState();

                    // Error if anything enabled TLS on a new connection before we can do it
                    if ($tlsState !== EncryptableSocket::TLS_STATE_DISABLED) {
                        $socket->close();

                        throw new UnprocessedRequestException(
                            new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')')
                        );
                    }

                    foreach ($request->getEventListeners() as $eventListener) {
                        yield $eventListener->startTlsNegotiation($request);
                    }

                    $tlsCancellationToken = new CombinedCancellationToken(
                        $cancellationToken,
                        new TimeoutCancellationToken($request->getTlsHandshakeTimeout())
                    );

                    yield $socket->setupTls($tlsCancellationToken);

                    foreach ($request->getEventListeners() as $eventListener) {
                        yield $eventListener->completeTlsNegotiation($request);
                    }
                } catch (StreamException $exception) {
                    $socket->close();

                    throw new UnprocessedRequestException(new SocketException(\sprintf(
                        "Connection to '%s' @ '%s' closed during TLS handshake",
                        $authority,
                        $socket->getRemoteAddress()->toString()
                    ), 0, $exception));
                } catch (CancelledException $e) {
                    $socket->close();

                    // In case of a user cancellation request, throw the expected exception
                    $cancellationToken->throwIfRequested();

                    // Otherwise we ran into a timeout of our TimeoutCancellationToken
                    throw new UnprocessedRequestException(new TimeoutException(\sprintf(
                        "TLS handshake with '%s' @ '%s' timed out, took longer than " . $request->getTlsHandshakeTimeout() . ' ms',
                        $authority,
                        $socket->getRemoteAddress()->toString()
                    ))); // don't pass $e
                }

                $tlsInfo = $socket->getTlsInfo();
                if ($tlsInfo === null) {
                    throw new UnprocessedRequestException(
                        new SocketException(\sprintf(
                            "Socket closed after TLS handshake with '%s' @ '%s'",
                            $authority,
                            $socket->getRemoteAddress()->toString()
                        ))
                    );
                }

                if ($tlsInfo->getApplicationLayerProtocol() === 'h2') {
                    $http2Connection = new Http2Connection($socket);
                    yield $http2Connection->initialize();

                    foreach ($request->getEventListeners() as $eventListener) {
                        yield $eventListener->completeConnectionCreation($request);
                    }

                    return $http2Connection;
                }
            }

            // Treat the presence of only HTTP/2 as prior knowledge, see https://http2.github.io/http2-spec/#known-http
            if ($request->getProtocolVersions() === ['2']) {
                $http2Connection = new Http2Connection($socket);
                yield $http2Connection->initialize();

                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->completeConnectionCreation($request);
                }

                return $http2Connection;
            }

            if (!\array_intersect($request->getProtocolVersions(), ['1.0', '1.1'])) {
                $socket->close();

                throw new InvalidRequestException(
                    $request,
                    \sprintf(
                        "None of the requested protocol versions (%s) are supported by '%s' @ '%s'",
                        \implode(', ', $protocolVersions),
                        $authority,
                        $socket->getRemoteAddress()->toString()
                    )
                );
            }

            foreach ($request->getEventListeners() as $eventListener) {
                yield $eventListener->completeConnectionCreation($request);
            }

            return new Http1Connection($socket);
        });
    }
}
