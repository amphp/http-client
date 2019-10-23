<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\NullCancellationToken;
use Amp\Promise;

final class InterceptedHttpClient implements HttpClient
{
    /** @var HttpClient */
    private $httpClient;

    /** @var ApplicationInterceptor */
    private $interceptor;

    public function __construct(HttpClient $httpClient, ApplicationInterceptor $interceptor)
    {
        $this->httpClient = $httpClient;
        $this->interceptor = $interceptor;
    }

    public function request(Request $request, ?CancellationToken $cancellation = null): Promise
    {
        $cancellation = $cancellation ?? new NullCancellationToken;

        return $this->interceptor->request(clone $request, $cancellation, $this->httpClient);
    }
}
