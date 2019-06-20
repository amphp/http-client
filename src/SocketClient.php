<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\CallableNetworkClient;
use Amp\Http\Client\Internal\NetworkInterceptorClient;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket\Connector;
use Amp\Socket\DnsConnector;
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

            $connection = yield $this->connectionPool->getConnection($request, $cancellation);
            \assert($connection instanceof Connection\Connection);

            $client = new CallableNetworkClient(function () use ($connection, $request, $cancellation): Promise {
                return $connection->request($request, $cancellation);
            });

            $client = new NetworkInterceptorClient($client, $connection->getConnectionInfo(), ...$this->networkInterceptors);

            return yield $client->request($request, $cancellation);
        });
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
