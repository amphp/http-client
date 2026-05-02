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
    /**
     * @param EventListener[] $eventListeners
     */
    public function __construct(
        private readonly DelegateHttpClient $httpClient,
        private readonly array $eventListeners,
    ) {
    }

    /**
     * Request a specific resource from an HTTP server.
     *
     * @throws HttpException
     */
    #[\Override]
    public function request(Request $request, ?Cancellation $cancellation = null): Response
    {
        return processRequest(
            $request,
            $this->eventListeners,
            fn () => $this->httpClient->request($request, $cancellation ?? new NullCancellation()),
        );
    }
}
