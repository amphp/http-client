<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\NullCancellationToken;
use Amp\Promise;

final class InterceptedHttpClient implements DelegateHttpClient
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var DelegateHttpClient */
    private $httpClient;

    /** @var ApplicationInterceptor[] */
    private $interceptors;

    /** @var string */
    private $attributeKey;

    public function __construct(DelegateHttpClient $httpClient, ApplicationInterceptor ...$interceptors)
    {
        $this->httpClient = $httpClient;
        $this->interceptors = $interceptors;

        $this->attributeKey = self::class . '.' . \spl_object_hash($this);
    }

    public function request(Request $request, CancellationToken $cancellation): Promise
    {
        $cancellation = $cancellation ?? new NullCancellationToken;
        $request = clone $request;

        if ($request->hasAttribute($this->attributeKey)) {
            $interceptorIndex = $request->getAttribute($this->attributeKey) + 1;
        } else {
            $interceptorIndex = 0;
        }

        if ($interceptorIndex < \count($this->interceptors)) {
            $request->setAttribute($this->attributeKey, $interceptorIndex);
            return $this->interceptors[$interceptorIndex]->request($request, $cancellation, $this);
        }

        if ($request->hasAttribute($this->attributeKey)) {
            $request->removeAttribute($this->attributeKey);
        }

        return $this->httpClient->request($request, $cancellation);
    }
}
