<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * Base HTTP client interface for use in {@see ApplicationInterceptor}.
 *
 * Applications and implementations should depend on {@see HttpClient} instead. The intent of this interface is to allow
 * static analysis tools to find interceptors that forget to pass the cancellation token down. This situation is created
 * because of the cancellation token being optional.
 *
 * @see HttpClient
 */
interface DelegateHttpClient
{
    /**
     * Request a specific resource from an HTTP server.
     *
     * Note: Each client implementation MUST clone the given request before any modification or before passing the
     * request to another object. This ensures that interceptors don't have to care about cloning and work reliably
     * even if requests are retried.
     *
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Promise<Response>
     */
    public function request(Request $request, CancellationToken $cancellation): Promise;
}
