<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\ForbidSerialization;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\InterceptedStream;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;

final class PooledHttpClient implements DelegateHttpClient
{
    use ForbidSerialization;

    private ConnectionPool $connectionPool;

    /** @var NetworkInterceptor[] */
    private array $networkInterceptors = [];

    private array $eventListeners = [];

    public function __construct(?ConnectionPool $connectionPool = null)
    {
        $this->connectionPool = $connectionPool ?? new UnlimitedConnectionPool;
    }

    public function request(Request $request, Cancellation $cancellation): Response
    {
        return processRequest($request, $this->eventListeners, function () use ($request, $cancellation) {
            $stream = $this->connectionPool->getStream($request, $cancellation);

            foreach (\array_reverse($this->networkInterceptors) as $interceptor) {
                $stream = new InterceptedStream($stream, $interceptor);
            }

            return $stream->request($request, $cancellation);
        });
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

    /**
     * Adds an event listener.
     *
     * Returns a new PooledHttpClient instance with the listener attached.
     */
    public function listen(EventListener $eventListener): self
    {
        $clone = clone $this;
        $clone->eventListeners[] = $eventListener;

        return $clone;
    }

    final protected function __clone()
    {
        // clone is automatically denied to all external calls
        // final protected instead of private to also force denial for all children
    }
}
