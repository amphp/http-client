<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;

abstract class ConditionalInterceptor implements ApplicationInterceptor, NetworkInterceptor
{
    private $interceptor;

    /**
     * @param ApplicationInterceptor|NetworkInterceptor $interceptor
     *
     * @throws \TypeError
     */
    public function __construct($interceptor)
    {
        if (!$interceptor instanceof ApplicationInterceptor && !$interceptor instanceof NetworkInterceptor) {
            throw new \TypeError('$interceptor must be an instance of ApplicationInterceptor or NetworkInterceptor');
        }

        $this->interceptor = $interceptor;
    }

    final public function request(Request $request, CancellationToken $cancellation, DelegateHttpClient $next): Promise
    {
        if ($this->interceptor instanceof ApplicationInterceptor && $this->matches($request)) {
            return $this->interceptor->request($request, $cancellation, $next);
        }

        return $next->request($request, $cancellation);
    }

    final public function requestViaNetwork(Request $request, CancellationToken $cancellation, Stream $stream): Promise
    {
        if ($this->interceptor instanceof NetworkInterceptor && $this->matches($request)) {
            return $this->interceptor->requestViaNetwork($request, $cancellation, $stream);
        }

        return $stream->request($request, $cancellation);
    }

    abstract protected function matches(Request $request): bool;
}
