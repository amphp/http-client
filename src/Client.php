<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\InterceptedStream;
use Amp\NullCancellationToken;
use Amp\Promise;
use Psr\Http\Message\UriInterface;
use function Amp\call;

/**
 * The HTTP client weaving all parts together.
 */
final class Client
{
    /** @var ConnectionPool */
    private $connectionPool;
    /** @var ApplicationInterceptor[] */
    private $applicationInterceptors;
    /** @var NetworkInterceptor[] */
    private $networkInterceptors;

    public function __construct(?ConnectionPool $connectionPool = null)
    {
        $this->connectionPool = $connectionPool ?? new DefaultConnectionPool;
        $this->applicationInterceptors = [];
        $this->networkInterceptors = [];
    }

    /**
     * Asynchronously request an HTTP resource.
     *
     * @param Request|UriInterface|string $requestOrUri A Request / UriInterface instance or URL as string.
     * @param CancellationToken           $cancellation A cancellation token to optionally cancel requests.
     *
     * @return Promise A promise to resolve to a response object as soon as its headers are received.
     */
    public function request($requestOrUri, ?CancellationToken $cancellation = null): Promise
    {
        $cancellation = $cancellation ?? new NullCancellationToken;

        if ($requestOrUri instanceof Request) {
            $request = $requestOrUri;
        } else {
            $request = new Request($requestOrUri);
        }

        return call(function () use ($request, $cancellation) {
            if ($this->applicationInterceptors) {
                $client = clone $this;
                $interceptor = \array_shift($client->applicationInterceptors);
                $response = yield $interceptor->interceptApplication($request, $cancellation, $client);
            } else {
                $stream = yield $this->connectionPool->getStream($request, $cancellation);
                \assert($stream instanceof Stream);

                if ($this->networkInterceptors) {
                    $stream = new InterceptedStream($stream, ...$this->networkInterceptors);
                }

                $response = yield $stream->request($request, $cancellation);
            }

            return $response;
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
     */
    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void
    {
        $this->networkInterceptors[] = $networkInterceptor;
    }

    /**
     * Adds an application interceptor.
     *
     * Whether the given application interceptor will be respected for currently running requests is undefined.
     *
     * Any new requests have to take the new interceptor into account.
     *
     * @param ApplicationInterceptor $applicationInterceptor
     */
    public function addApplicationInterceptor(ApplicationInterceptor $applicationInterceptor): void
    {
        $this->applicationInterceptors[] = $applicationInterceptor;
    }
}
