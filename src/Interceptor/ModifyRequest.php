<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;
use function Amp\call;

class ModifyRequest implements NetworkInterceptor, ApplicationInterceptor
{
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
            $request = (yield call($this->mapper, $request)) ?? $request;
            return $stream->request($request, $cancellation);
        });
    }

    public function request(Request $request, CancellationToken $cancellation, HttpClient $next): Promise
    {
        return call(function () use ($request, $cancellation, $next) {
            $request = (yield call($this->mapper, $request)) ?? $request;
            return $next->request($request, $cancellation);
        });
    }
}
