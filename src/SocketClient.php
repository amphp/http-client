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
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\DnsConnector;
use Amp\Socket\EncryptableSocket;
use Amp\TimeoutCancellationToken;
use function Amp\call;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class SocketClient implements Client
{
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; amphp/http-client)';

    private $connector;
    private $connectionPool;
    private $networkInterceptors;

    public function __construct(?Connector $connector = null, ?Connection\ConnectionPool $driverFactory = null)
    {
        $this->connector = $connector ?? new DnsConnector;
        $this->connectionPool = $driverFactory ?? new Connection\DefaultConnectionPool;
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

            $request = $this->normalizeRequestHeaders($request);

            $connection = $this->connectionPool->getConnection($request);

            if ($connection === null) {
                $socket = yield from $this->createSocket($request, $cancellation);
                $connection = $this->connectionPool->createConnection($socket, $request);
            }

            $client = new CallableNetworkClient(function () use ($connection, $request, $cancellation): Promise {
                return $connection->request($request, $cancellation);
            });

            $client = new NetworkInterceptorClient($client, $connection->getConnectionInfo(), ...$this->networkInterceptors);

            return yield $client->request($request, $cancellation);
        });
    }

    private function createSocket(Request $request, CancellationToken $cancellation): \Generator
    {
        $isHttps = $request->getUri()->getScheme() === 'https';
        $defaultPort = $isHttps ? 443 : 80;

        $authority = $request->getUri()->getHost() . ':' . ($request->getUri()->getPort() ?: $defaultPort);
        $socketUri = "tcp://{$authority}";

        $connectContext = new ConnectContext;

        if ($request->getUri()->getScheme() === 'https') {
            $tlsContext = ($connectContext->getTlsContext() ?? new ClientTlsContext($request->getUri()->getHost()))
                ->withPeerName($request->getUri()->getHost())
                ->withApplicationLayerProtocols($this->connectionPool->getApplicationLayerProtocols())
                ->withPeerCapturing();

            $connectContext = $connectContext->withTlsContext($tlsContext);
        }

        try {
            $checkoutCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTcpConnectTimeout()));

            /** @var EncryptableSocket $socket */
            $socket = yield $this->connector->connect($socketUri, $connectContext, $checkoutCancellationToken);
        } catch (SocketException $e) {
            throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
        } catch (CancelledException $e) {
            // In case of a user cancellation request, throw the expected exception
            $cancellation->throwIfRequested();

            // Otherwise we ran into a timeout of our TimeoutCancellationToken
            throw new TimeoutException(\sprintf("Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms', $authority)); // don't pass $e
        }

        if (!$isHttps) {
            return $socket;
        }

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

        return $socket;
    }

    private function normalizeRequestHeaders(Request $request): Request
    {
        $request = $this->normalizeRequestHostHeader($request);
        $request = $this->normalizeRequestUserAgent($request);
        $request = $this->normalizeRequestAcceptHeader($request);

        return $request;
    }

    private function normalizeRequestHostHeader(Request $request): Request
    {
        if ($request->hasHeader('host')) {
            $host = $request->getHeader('host');
        } else {
            $host = $request->getUri()->withUserInfo('')->getAuthority();
        }

        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80 or 443
        if ($request->getUri()->getScheme() === 'http' && \strpos($host, ':80') === \strlen($host) - 3) {
            $request = $request->withHeader('host', \substr($host, 0, -3));
        } elseif ($request->getUri()->getScheme() === 'https' && \strpos($host, ':443') === \strlen($host) - 4) {
            $request = $request->withHeader('host', \substr($host, 0, -4));
        } else {
            $request = $request->withHeader('host', $host);
        }

        return $request;
    }

    private function normalizeRequestUserAgent(Request $request): Request
    {
        if ($request->hasHeader('user-agent')) {
            return $request;
        }

        return $request->withHeader('user-agent', self::DEFAULT_USER_AGENT);
    }

    private function normalizeRequestAcceptHeader(Request $request): Request
    {
        if ($request->hasHeader('accept')) {
            return $request;
        }

        return $request->withHeader('accept', '*/*');
    }
}
