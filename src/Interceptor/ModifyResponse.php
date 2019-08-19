<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use function Amp\call;

class ModifyResponse implements NetworkInterceptor, ApplicationInterceptor
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
            /** @var Response $response */
            $response = yield $stream->request($request, $cancellation);
            return call($this->mapper, $response);
        });
    }

    public function request(Request $request, CancellationToken $cancellation, Client $client): Promise
    {
        return call(function () use ($request, $cancellation, $client) {
            if ($onPush = $request->getPushCallable()) {
                $request->onPush(function (Request $request, Promise $promise, CancellationTokenSource $source) use ($onPush) {
                    $promise = call(function () use ($promise) {
                        $response = yield $promise;
                        return yield call($this->mapper, $response);
                    });

                    return $onPush($request, $promise, $source);
                });
            }

            /** @var Response $response */
            $response = yield $client->request($request, $cancellation);
            return call($this->mapper, $response);
        });
    }
}
