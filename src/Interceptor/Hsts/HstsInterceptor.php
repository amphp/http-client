<?php

namespace Amp\Http\Client\Interceptor\Hsts;

use Amp\Cancellation;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

final class HstsInterceptor implements ApplicationInterceptor
{
    public function __construct(private readonly ReadableHstsJar $hstsJar)
    {
    }

    public function request(Request $request, Cancellation $cancellation, DelegateHttpClient $httpClient): Response
    {
        if ($request->getUri()->getScheme() === "http" && $this->hstsJar->test($request->getUri()->getHost())) {
            $request->setUri($request->getUri()->withScheme("https"));
        }
        $response = $httpClient->request($request, $cancellation);
        if ($strictTransportSecurity = $response->getHeader("Strict-Transport-Security")) {
            $directives = \array_map(trim(...), \explode(";", $strictTransportSecurity));
            $includeSubDomains = false;
            $remove = false;
            foreach ($directives as $directive) {
                if ($directive === "includeSubDomains") {
                    $includeSubDomains = true;
                } elseif ($directive === "max-age=0") {
                    $remove = true;
                }
            }
            if ($this->hstsJar instanceof HstsJar) {
                if ($remove) {
                    $this->hstsJar->unregister($request->getUri()->getHost());
                } else {
                    $this->hstsJar->register($request->getUri()->getHost(), $includeSubDomains);
                }
            }
        }
        return $response;
    }
}
