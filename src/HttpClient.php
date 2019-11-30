<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\NullCancellationToken;
use Amp\Promise;

/**
 * Convenient HTTP client for use in applications and libraries, providing a default for the cancellation token and
 * automatically cloning the passed request, so future application requests can re-use the same object again.
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
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Promise<Response>
     */
    public function request(Request $request, ?CancellationToken $cancellation = null): Promise
    {
        return $this->httpClient->request(clone $request, $cancellation ?? new NullCancellationToken);
    }
}
