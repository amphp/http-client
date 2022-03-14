<?php

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\InterceptedStream;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;

final class PooledHttpClient implements DelegateHttpClient
{
    use ForbidCloning;
    use ForbidSerialization;

    private ConnectionPool $connectionPool;

    /** @var NetworkInterceptor[] */
    private array $networkInterceptors = [];

    public function __construct(?ConnectionPool $connectionPool = null)
    {
        $this->connectionPool = $connectionPool ?? new UnlimitedConnectionPool;
    }

    public function request(Request $request, Cancellation $cancellation): Response
    {
        foreach ($request->getEventListeners() as $eventListener) {
            $eventListener->startRequest($request);
        }

        $stream = $this->connectionPool->getStream($request, $cancellation);

        foreach (\array_reverse($this->networkInterceptors) as $interceptor) {
            $stream = new InterceptedStream($stream, $interceptor);
        }

        return $stream->request($request, $cancellation);
    }

    /**
     * Adds a network interceptor.
     *
     * Network interceptors are only invoked if the request requires network access, i.e. there's no short-circuit by
     * an application interceptor, e.g. a cache.
     *
     * Whether the given network interceptor will be respected for currently running requests is undefined.
     *
     * Any new requests have to take the new interceptor into account.
     */
    public function intercept(NetworkInterceptor $networkInterceptor): self
    {
        $clone = clone $this;
        $clone->networkInterceptors[] = $networkInterceptor;

        return $clone;
    }
}
