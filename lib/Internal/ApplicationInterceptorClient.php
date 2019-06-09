<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Client;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\NullCancellationToken;
use Amp\Promise;

/** @internal */
final class ApplicationInterceptorClient implements Client
{
    /** @var Client */
    private $client;
    /** @var ApplicationInterceptor */
    private $interceptor;

    public function __construct(
        Client $client,
        ApplicationInterceptor $interceptor
    ) {
        $this->client = $client;
        $this->interceptor = $interceptor;
    }

    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
        $cancellation = $cancellation ?? new NullCancellationToken;

        return $this->interceptor->interceptApplicationRequest($request, $cancellation, $this->client);
    }

    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void
    {
        throw new \RuntimeException('Operation not supported');
    }
}
