<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\NullCancellationToken;
use Amp\Promise;

/** @internal */
final class NetworkInterceptorClient implements Client
{
    /** @var Client */
    private $client;
    /** @var Connection */
    private $connection;
    /** @var NetworkInterceptor[] */
    private $interceptors;

    public function __construct(
        Client $client,
        Connection $connection,
        NetworkInterceptor ...$networkInterceptors
    ) {
        $this->client = $client;
        $this->connection = $connection;
        $this->interceptors = $networkInterceptors;
    }

    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
        if (!$this->interceptors) {
            return $this->client->request($request, $cancellation);
        }

        $cancellation = $cancellation ?? new NullCancellationToken;
        $interceptor = $this->interceptors[0];
        $remainingInterceptors = \array_slice($this->interceptors, 1);
        $next = new self($this->client, $this->connection, ...$remainingInterceptors);

        return $interceptor->interceptNetworkRequest($request, $cancellation, $this->connection, $next);
    }

    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void
    {
        throw new \RuntimeException('Operation not supported');
    }
}
