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

class ModifyResponse implements NetworkInterceptor, ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var callable(Response):(\Generator<mixed, mixed, mixed, Response|null>|Promise<Response>|Response|null) */
    private $mapper;

    /**
     * @psalm-param callable(Response):(\Generator<mixed, mixed, mixed, Response|null>|Promise<Response>|Response|null) $mapper
     */
    public function __construct(callable $mapper)
    {
        $this->mapper = $mapper;
    }

    final public function requestViaNetwork(
        Request $request,
        CancellationToken $cancellation,
        Stream $stream
    ): Response {
        $response = $stream->request($request, $cancellation);
        $mappedResponse = ($this->mapper)($response);

        \assert($mappedResponse instanceof Response || $mappedResponse === null);

        return $mappedResponse ?? $response;
    }

    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Response {
        $request->interceptPush($this->mapper);

        $response = $httpClient->request($request, $cancellation);
        $mappedResponse = ($this->mapper)($response);

        \assert($mappedResponse instanceof Response || $mappedResponse === null);

        return $mappedResponse ?? $response;
    }
}
