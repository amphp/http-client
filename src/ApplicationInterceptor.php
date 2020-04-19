<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Interceptor\DecompressResponse;
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
     * Any retry or cloned follow-up request must be manually cloned from `$request` to ensure a properly working
     * interceptor chain, e.g. the {@see DecompressResponse} interceptor only decodes a response if the
     * `accept-encoding` header isn't set manually. If the request isn't cloned, the first attempt will set the header
     * and the second attempt will see the header and won't decode the response, because it thinks another interceptor
     * or the application itself will care about the decoding.
     *
     * @param Request            $request
     * @param CancellationToken  $cancellation
     * @param DelegateHttpClient $httpClient
     *
     * @return Promise<Response>
     */
    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Promise;
}
