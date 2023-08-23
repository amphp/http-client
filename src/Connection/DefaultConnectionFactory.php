<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\TimeoutCancellation;
use function Amp\Http\Client\events;

final class DefaultConnectionFactory implements ConnectionFactory
{
    private ?Socket\SocketConnector $connector;

    private ?ConnectContext $connectContext;

    public function __construct(?Socket\SocketConnector $connector = null, ?ConnectContext $connectContext = null)
    {
        $this->connector = $connector;
        $this->connectContext = $connectContext;
    }

    public function create(Request $request, Cancellation $cancellation): Connection
    {
        events()->connectStart($request);

        $connector = $this->connector ?? Socket\socketConnector();
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
                ->withApplicationLayerProtocols($protocols)
                ->withPeerCapturing();

            if ($protocols === ['http/1.1']) {
                // If we only have HTTP/1.1 available, don't set application layer protocols.
                // There are misbehaving sites like n11.com, see https://github.com/amphp/http-client/issues/255
                $tlsContext = $tlsContext->withApplicationLayerProtocols([]);
            }

            if ($tlsContext->getPeerName() === '') {
                $tlsContext = $tlsContext->withPeerName($host);
            }

            $connectContext = $connectContext->withTlsContext($tlsContext);
        }

        try {
            $socket = $connector->connect(
                'tcp://' . $authority,
                $connectContext->withConnectTimeout($request->getTcpConnectTimeout()),
                $cancellation
            );
        } catch (Socket\ConnectException $e) {
            throw new UnprocessedRequestException(new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e));
        } catch (CancelledException) {
            // In case of a user cancellation request, throw the expected exception
            $cancellation->throwIfRequested();

            // Otherwise we ran into a timeout of our TimeoutCancellation
            throw new UnprocessedRequestException(new SocketException(\sprintf(
                "Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' s',
                $authority
            )));
        }

        if ($isHttps) {
            events()->tlsHandshakeStart($request);

            try {
                $tlsState = $socket->getTlsState();

                // Error if anything enabled TLS on a new connection before we can do it
                if ($tlsState !== Socket\TlsState::Disabled) {
                    $socket->close();

                    throw new UnprocessedRequestException(new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState->name . ')'));
                }

                $socket->setupTls(new CompositeCancellation(
                    $cancellation,
                    new TimeoutCancellation($request->getTlsHandshakeTimeout())
                ));
            } catch (StreamException $exception) {
                $socket->close();

                throw new UnprocessedRequestException(new SocketException(\sprintf(
                    "Connection to '%s' @ '%s' closed during TLS handshake",
                    $authority,
                    $socket->getRemoteAddress()->toString()
                ), 0, $exception));
            } catch (CancelledException) {
                $socket->close();

                // In case of a user cancellation request, throw the expected exception
                $cancellation->throwIfRequested();

                // Otherwise we ran into a timeout of our TimeoutCancellation
                throw new UnprocessedRequestException(new TimeoutException(\sprintf(
                    "TLS handshake with '%s' @ '%s' timed out, took longer than " . $request->getTlsHandshakeTimeout() . ' s',
                    $authority,
                    $socket->getRemoteAddress()->toString()
                )));
            }

            $tlsInfo = $socket->getTlsInfo();
            if ($tlsInfo === null) {
                $socket->close();

                throw new UnprocessedRequestException(new SocketException(\sprintf(
                    "Socket closed after TLS handshake with '%s' @ '%s'",
                    $authority,
                    $socket->getRemoteAddress()->toString()
                )));
            }

            events()->tlsHandshakeEnd($request, $tlsInfo);

            if ($tlsInfo->getApplicationLayerProtocol() === 'h2') {
                $http2Connection = new Http2Connection($socket);
                $http2Connection->initialize($cancellation);

                events()->connectEnd($request, $http2Connection);

                return $http2Connection;
            }
        }

        // Treat the presence of only HTTP/2 as prior knowledge, see https://http2.github.io/http2-spec/#known-http
        if ($request->getProtocolVersions() === ['2']) {
            $http2Connection = new Http2Connection($socket);
            $http2Connection->initialize($cancellation);

            events()->connectEnd($request, $http2Connection);

            return $http2Connection;
        }

        if (!\array_intersect($request->getProtocolVersions(), ['1.0', '1.1'])) {
            $socket->close();

            throw new UnprocessedRequestException(new SocketException(\sprintf(
                "None of the requested protocol versions (%s) are supported by '%s' @ '%s'",
                \implode(', ', $protocolVersions),
                $authority,
                $socket->getRemoteAddress()->toString()
            )));
        }

        $http1Connection = new Http1Connection($socket);

        events()->connectEnd($request, $http1Connection);

        return $http1Connection;
    }
}
