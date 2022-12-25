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

class ModifyRequest implements NetworkInterceptor, ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var \Closure(Request):(Request|null) */
    private \Closure $mapper;

    /**
     * @param \Closure(Request):(Request|null) $mapper
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
        $mappedRequest = ($this->mapper)($request);

        \assert($mappedRequest instanceof Request || $mappedRequest === null);

        return $stream->request($mappedRequest ?? $request, $cancellation);
    }

    public function request(
        Request $request,
        Cancellation $cancellation,
        DelegateHttpClient $httpClient
    ): Response {
        $mappedRequest = ($this->mapper)($request);

        \assert($mappedRequest instanceof Request || $mappedRequest === null);

        return $httpClient->request($mappedRequest ?? $request, $cancellation);
    }
}
