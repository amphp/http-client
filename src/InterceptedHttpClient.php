<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;

final class InterceptedHttpClient implements DelegateHttpClient
{
    use ForbidCloning;
    use ForbidSerialization;

    private DelegateHttpClient $httpClient;

    private ApplicationInterceptor $interceptor;

    public function __construct(DelegateHttpClient $httpClient, ApplicationInterceptor $interceptor)
    {
        $this->httpClient = $httpClient;
        $this->interceptor = $interceptor;
    }

    public function request(Request $request, Cancellation $cancellation): Response
    {
        foreach ($request->getEventListeners() as $eventListener) {
            $eventListener->startRequest($request);
        }

        return $this->interceptor->request($request, $cancellation, $this->httpClient);
    }
}
