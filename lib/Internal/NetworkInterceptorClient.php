<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Http\Client\Client;
use Amp\Http\Client\ConnectionInfo;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\NullCancellationToken;
use Amp\Promise;

/** @internal */
final class NetworkInterceptorClient implements Client
{
    /** @var Client */
    private $client;
    /** @var ConnectionInfo */
    private $connectionInfo;
    /** @var NetworkInterceptor[] */
    private $interceptors;

    public function __construct(
        Client $client,
        ConnectionInfo $connectionInfo,
        NetworkInterceptor ...$networkInterceptors
    ) {
        $this->client = $client;
        $this->connectionInfo = $connectionInfo;
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
        $next = new self($this->client, $this->connectionInfo, ...$remainingInterceptors);

        return $interceptor->interceptNetworkRequest($request, $cancellation, $this->connectionInfo, $next);
    }
}
