<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;

final class InterceptedHttpClient implements DelegateHttpClient
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var DelegateHttpClient */
    private DelegateHttpClient $httpClient;

    /** @var ApplicationInterceptor */
    private ApplicationInterceptor $interceptor;

    public function __construct(DelegateHttpClient $httpClient, ApplicationInterceptor $interceptor)
    {
        $this->httpClient = $httpClient;
        $this->interceptor = $interceptor;
    }

    public function request(Request $request, CancellationToken $cancellation): Response
    {
        foreach ($request->getEventListeners() as $eventListener) {
            $eventListener->startRequest($request);
        }

        return $this->interceptor->request($request, $cancellation, $this->httpClient);
    }
}
