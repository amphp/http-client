<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\RetryRequests;
use Amp\Http\Client\Interceptor\SetRequestHeaderIfUnset;
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
    /** @var ApplicationInterceptor[] */
    private $defaultApplicationInterceptors;
    /** @var NetworkInterceptor[] */
    private $networkInterceptors = [];

    /** @var NetworkInterceptor[] */
    private $defaultNetworkInterceptors;

    public function __construct(?ConnectionPool $connectionPool = null, int $retryLimit = 3)
    {
        $this->connectionPool = $connectionPool ?? new DefaultConnectionPool;

        $this->followRedirects = new FollowRedirects;

        $this->defaultApplicationInterceptors = [
            new RetryRequests($retryLimit),
        ];

        // We want to set these by default if the user doesn't choose otherwise
        $this->defaultNetworkInterceptors = [
            new SetRequestHeaderIfUnset('accept', '*/*'),
            new SetRequestHeaderIfUnset('user-agent', 'amphp/http-client (v4.x)'),
            new DecompressResponse,
        ];
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
            $request = clone $requestOrUri;
        } else {
            $request = new Request($requestOrUri);
        }

        $client = clone $this;

        if ($client->defaultApplicationInterceptors) {
            $client->applicationInterceptors = \array_merge(
                $client->applicationInterceptors,
                $client->defaultApplicationInterceptors
            );
            $client->defaultApplicationInterceptors = [];
        }

        if ($client->applicationInterceptors) {
            $interceptor = \array_shift($client->applicationInterceptors);
            return $interceptor->request($request, $cancellation, $client);
        }

        if ($this->followRedirects) {
            $client = clone $this;
            $client->followRedirects = null;
            return $this->followRedirects->request($request, $cancellation, $client);
        }

        return call(function () use ($client, $request, $cancellation) {
            $stream = yield $client->connectionPool->getStream($request, $cancellation);

            \assert($stream instanceof Stream);

            $networkInterceptors = \array_merge($client->defaultNetworkInterceptors, $client->networkInterceptors);
            $stream = new InterceptedStream($stream, ...$networkInterceptors);

            return yield $stream->request($request, $cancellation);
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
    public function withNetworkInterceptor(NetworkInterceptor $networkInterceptor): self
    {
        $client = clone $this;
        $client->networkInterceptors[] = $networkInterceptor;
        return $client;
    }

    /**
     * Adds an application interceptor.
     *
     * Whether the given application interceptor will be respected for currently running requests is undefined.
     *
     * Any new requests have to take the new interceptor into account.
     *
     * @param ApplicationInterceptor $applicationInterceptor
     *
     * @return self
     */
    public function withApplicationInterceptor(ApplicationInterceptor $applicationInterceptor): self
    {
        $client = clone $this;
        $client->applicationInterceptors[] = $applicationInterceptor;
        return $client;
    }

    /**
     * Returns a client that will automatically request the URI supplied by a redirect response (3xx status codes)
     * and return that response instead of the redirect response.
     *
     * @return self
     */
    public function withFollowingRedirects(): self
    {
        $client = clone $this;
        $client->followRedirects = new FollowRedirects;
        return $client;
    }

    /**
     * Returns a client that will not follow redirect responses (3xx status codes).
     *
     * @return self
     */
    public function withoutFollowingRedirects(): self
    {
        $client = clone $this;
        $client->followRedirects = null;
        return $client;
    }
}
