<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use function Amp\call;

class ModifyResponse implements NetworkInterceptor, ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var callable */
    private $mapper;

    public function __construct(callable $mapper)
    {
        $this->mapper = $mapper;
    }

    final public function requestViaNetwork(
        Request $request,
        CancellationToken $cancellation,
        Stream $stream
    ): Promise {
        return call(function () use ($request, $cancellation, $stream) {
            /** @var Response $response */
            $response = yield $stream->request($request, $cancellation);
            return (yield call($this->mapper, $response)) ?? $response;
        });
    }

    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Promise {
        return call(function () use ($request, $cancellation, $httpClient) {
            $request->interceptPush($this->mapper);

            /** @var Response $response */
            $response = yield $httpClient->request($request, $cancellation);
            return (yield call($this->mapper, $response)) ?? $response;
        });
    }
}
