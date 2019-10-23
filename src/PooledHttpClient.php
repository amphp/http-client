<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\SetRequestHeaderIfUnset;
use Amp\Http\Client\Internal\InterceptedStream;
use Amp\NullCancellationToken;
use Amp\Promise;
use function Amp\call;

final class PooledHttpClient implements HttpClient
{
    /** @var ConnectionPool */
    private $connectionPool;

    /** @var NetworkInterceptor[] */
    private $networkInterceptors = [];

    /** @var NetworkInterceptor[] */
    private $defaultNetworkInterceptors;

    public function __construct(?ConnectionPool $connectionPool = null)
    {
        $this->connectionPool = $connectionPool ?? new DefaultConnectionPool;

        // We want to set these by default if the user doesn't choose otherwise
        $this->defaultNetworkInterceptors = [
            new SetRequestHeaderIfUnset('accept', '*/*'),
            new SetRequestHeaderIfUnset('user-agent', 'amphp/http-client @ v4.x'),
            new DecompressResponse,
        ];
    }

    public function request(Request $request, ?CancellationToken $cancellation = null): Promise
    {
        return call(function () use ($request, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;
            $request = clone $request;

            try {
                $stream = yield $this->connectionPool->getStream($request, $cancellation);

                \assert($stream instanceof Stream);

                $networkInterceptors = \array_merge($this->defaultNetworkInterceptors, $this->networkInterceptors);
                $stream = new InterceptedStream($stream, ...$networkInterceptors);

                return yield $stream->request($request, $cancellation);
            } catch (UnprocessedRequestException $exception) {
                while ($exception instanceof UnprocessedRequestException) {
                    $exception = $exception->getPrevious();
                }

                throw $exception;
            }
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
     *
     * @param NetworkInterceptor $networkInterceptor
     *
     * @return self
     */
    public function intercept(NetworkInterceptor $networkInterceptor): self
    {
        $clone = clone $this;
        $clone->networkInterceptors[] = $networkInterceptor;

        return $clone;
    }
}
