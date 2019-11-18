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
     * The current interceptor is determined based on a request attribute, so new requests will always begin the
     * chain from the beginning while cloned request instances will continue at the current position.
     *
     * @param Request               $request
     * @param CancellationToken     $cancellation
     * @param InterceptedHttpClient $httpClient
     *
     * @return Promise
     */
    public function request(
        Request $request,
        CancellationToken $cancellation,
        InterceptedHttpClient $httpClient
    ): Promise;
}
