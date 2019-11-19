<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * Allows intercepting an application request to an HTTP resource.
 */
interface ApplicationInterceptor
{
    /**
     * Intercepts an application request to an HTTP resource.
     *
     * The implementation might modify the request, delegate the request handling to the `$httpClient`, and/or modify
     * the response after the promise returned from `$httpClient->request(...)` resolves.
     *
     * An interceptor might also short-circuit and not delegate to the `$httpClient` at all.
     *
     * @param Request            $request
     * @param CancellationToken  $cancellation
     * @param DelegateHttpClient $httpClient
     *
     * @return Promise
     */
    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Promise;
}
