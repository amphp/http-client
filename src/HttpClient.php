<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * Less strict HTTP client interface for use in applications and libraries.
 *
 * This interface makes the cancellation token optional, so applications and libraries using an HttpClient don't have
 * to pass a token if they don't need cancellation support.
 *
 * Applications and implementations should depend on this interface instead of {@see DelegateHttpClient}.
 *
 * @see HttpClient
 */
interface HttpClient extends DelegateHttpClient
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
    public function request(Request $request, ?CancellationToken $cancellation = null): Promise;
}
