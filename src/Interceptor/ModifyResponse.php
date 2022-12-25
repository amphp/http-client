<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

class ModifyResponse implements NetworkInterceptor, ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var \Closure(Response):Response */
    private \Closure $mapper;

    /**
     * @param \Closure(Response):Response $mapper
     */
    public function __construct(\Closure $mapper)
    {
        $this->mapper = $mapper;
    }

    final public function requestViaNetwork(
        Request $request,
        Cancellation $cancellation,
        Stream $stream
    ): Response {
        $response = $stream->request($request, $cancellation);
        $mappedResponse = ($this->mapper)($response);

        \assert($mappedResponse instanceof Response || $mappedResponse === null);

        return $mappedResponse ?? $response;
    }

    public function request(
        Request $request,
        Cancellation $cancellation,
        DelegateHttpClient $httpClient
    ): Response {
        $request->interceptPush(fn (Request $request, Response $response) => ($this->mapper)($response));

        $response = $httpClient->request($request, $cancellation);
        $mappedResponse = ($this->mapper)($response);

        \assert($mappedResponse instanceof Response || $mappedResponse === null);

        return $mappedResponse ?? $response;
    }
}
