<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\Http\Client\Interceptor\DecompressResponse;

/**
 * Allows intercepting an application request to an HTTP resource.
 */
interface ApplicationInterceptor
{
    /**
     * Intercepts an application request to an HTTP resource.
     *
     * The implementation might modify the request, delegate the request handling to the `$httpClient`, and/or modify
     * the response after `$httpClient->request(...)` returns.
     *
     * An interceptor might also short-circuit and not delegate to the `$httpClient` at all.
     *
     * Any retry or follow-up request must use a new request instance instead of reusing `$request` to ensure a properly
     * working interceptor chain, e.g. the {@see DecompressResponse} interceptor only decodes a response if the
     * `accept-encoding` header isn't set manually. If the request is reused, the first attempt will set the header
     * and the second attempt will see the header and won't decode the response, because it thinks another interceptor
     * or the application itself will care about the decoding.
     */
    public function request(
        Request $request,
        Cancellation $cancellation,
        DelegateHttpClient $httpClient
    ): Response;
}
