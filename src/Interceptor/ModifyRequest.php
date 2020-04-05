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

class ModifyRequest implements NetworkInterceptor, ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var callable(Request):(\Generator<mixed, mixed, mixed, Request|null>|Promise<Request>|Request|null) */
    private $mapper;

    /**
     * @psalm-param callable(Request):(\Generator<mixed, mixed, mixed, Request|null>|Promise<Request>|Request|null) $mapper
     */
    public function __construct(callable $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @param Request           $request
     * @param CancellationToken $cancellation
     * @param Stream            $stream
     *
     * @return Promise<Response>
     */
    final public function requestViaNetwork(
        Request $request,
        CancellationToken $cancellation,
        Stream $stream
    ): Promise {
        return call(function () use ($request, $cancellation, $stream) {
            $mappedRequest = yield call($this->mapper, $request);

            return yield $stream->request($mappedRequest ?? $request, $cancellation);
        });
    }

    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Promise {
        return call(function () use ($request, $cancellation, $httpClient) {
            $mappedRequest = yield call($this->mapper, $request);

            return $httpClient->request($mappedRequest ?? $request, $cancellation);
        });
    }
}
