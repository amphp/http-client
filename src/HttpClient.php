<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\NullCancellation;

/**
 * Convenient HTTP client for use in applications and libraries, providing a default for the cancellation and
 * automatically cloning the passed request, so future application requests can re-use the same object again.
 */
final class HttpClient implements DelegateHttpClient
{
    private DelegateHttpClient $httpClient;

    public function __construct(DelegateHttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Request a specific resource from an HTTP server.
     */
    public function request(Request $request, ?Cancellation $cancellation = null): Response
    {
        return $this->httpClient->request($request, $cancellation ?? new NullCancellation);
    }
}
