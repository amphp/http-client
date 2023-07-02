<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;

final class InterceptedHttpClient implements DelegateHttpClient
{
    use ForbidCloning;
    use ForbidSerialization;

    private static \WeakMap $requestInterceptors;

    private DelegateHttpClient $httpClient;

    private ApplicationInterceptor $interceptor;

    public function __construct(DelegateHttpClient $httpClient, ApplicationInterceptor $interceptor)
    {
        $this->httpClient = $httpClient;
        $this->interceptor = $interceptor;
    }

    public function request(Request $request, Cancellation $cancellation): Response
    {
        return processRequest($request, function () use ($request, $cancellation) {
            /** @psalm-suppress RedundantPropertyInitializationCheck */
            self::$requestInterceptors ??= new \WeakMap();
            $requestInterceptors = self::$requestInterceptors[$request] ?? [];
            $requestInterceptors[] = $this->interceptor;
            self::$requestInterceptors[$request] = $requestInterceptors;

            events()->applicationInterceptorStart($request, $this->interceptor);

            $response = $this->interceptor->request($request, $cancellation, $this->httpClient);

            events()->applicationInterceptorEnd($request, $this->interceptor, $response);

            return $response;
        });
    }
}
