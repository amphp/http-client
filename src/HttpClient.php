<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\NullCancellationToken;
use Amp\Promise;

/**
 * Less strict HTTP client for use in applications and libraries.
 *
 * This class makes the cancellation token optional, so applications and libraries using an HttpClient don't have
 * to pass a token if they don't need cancellation support.
 */
final class HttpClient implements DelegateHttpClient
{
    private $httpClient;

    public function __construct(DelegateHttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

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
    public function request(Request $request, ?CancellationToken $cancellation = null): Promise
    {
        return $this->httpClient->request($request, $cancellation ?? new NullCancellationToken);
    }
}
