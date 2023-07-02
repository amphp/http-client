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

    /** @var EventListener[] */
    private array $eventListeners;

    public function __construct(DelegateHttpClient $httpClient, array $eventListeners)
    {
        $this->httpClient = $httpClient;
        $this->eventListeners = $eventListeners;
    }

    /**
     * Request a specific resource from an HTTP server.
     *
     * @throws HttpException
     */
    public function request(Request $request, ?Cancellation $cancellation = null): Response
    {
        return processRequest(
            $request,
            $this->eventListeners,
            fn () => $this->httpClient->request($request, $cancellation ?? new NullCancellation())
        );
    }
}
