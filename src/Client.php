<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Failure;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\FollowRedirects;
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
    private $applicationInterceptors = [];

    /** @var NetworkInterceptor[] */
    private $networkInterceptors = [];

    /** @var NetworkInterceptor[] */
    private $defaultNetworkInterceptors;

    /** @var RetryRequests|null */
    private $retryInterceptor;

    /** @var FollowRedirects|null */
    private $followRedirectsInterceptor;

    public function __construct(?ConnectionPool $connectionPool = null)
    {
        $this->connectionPool = $connectionPool ?? new DefaultConnectionPool;

        $this->followRedirectsInterceptor = new FollowRedirects(10);
        $this->retryInterceptor = new RetryRequests(2);

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
     * @return Promise<Response> A promise to resolve to a response object as soon as its headers are received.
     */
    public function request($requestOrUri, ?CancellationToken $cancellation = null): Promise
    {
        $cancellation = $cancellation ?? new NullCancellationToken;

        if ($requestOrUri instanceof Request) {
            $request = clone $requestOrUri;
        } else {
            $request = new Request($requestOrUri);
        }

        if ($request->getUri()->getUserInfo() !== '') {
            return new Failure(new HttpException('The user information (username:password) component of URIs has been deprecated '
                . '(see https://tools.ietf.org/html/rfc3986#section-3.2.1 and https://tools.ietf.org/html/rfc7230#section-2.7.1); '
                . 'Instead, set an "Authorization" header containing "Basic " . \\base64_encode("username:password")'));
        }

        if ($this->applicationInterceptors) {
            $client = clone $this;
            $interceptor = \array_shift($client->applicationInterceptors);
            return $interceptor->request($request, $cancellation, $client);
        }

        if ($this->followRedirectsInterceptor) {
            $client = clone $this;
            $client->followRedirectsInterceptor = null;
            return $this->followRedirectsInterceptor->request($request, $cancellation, $client);
        }

        if ($this->retryInterceptor) {
            $client = clone $this;
            $client->retryInterceptor = null;
            return $this->retryInterceptor->request($request, $cancellation, $client);
        }

        return call(function () use ($request, $cancellation) {
            $stream = yield $this->connectionPool->getStream($request, $cancellation);

            \assert($stream instanceof Stream);

            $networkInterceptors = \array_merge($this->defaultNetworkInterceptors, $this->networkInterceptors);
            $stream = new InterceptedStream($stream, ...$networkInterceptors);

            try {
                return yield $stream->request($request, $cancellation);
            } catch (UnprocessedRequestException $exception) {
                throw $exception->getPrevious();
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
     * @param int $retryLimit Maximum number of times a request may be retried. Only certain requests will be retried
     *                        automatically (GET, HEAD, PUT, and DELETE requests are automatically retried, or any
     *                        request that was indicated as unprocessed by the HTTP server).
     *
     * @return self
     */
    public function withRetryLimit(int $retryLimit): self
    {
        $client = clone $this;

        if ($retryLimit <= 0) {
            $client->retryInterceptor = null;
        } else {
            $client->retryInterceptor = new RetryRequests($retryLimit);
        }

        return $client;
    }

    /**
     * Returns a client that will automatically request the URI supplied by a redirect response (3xx status codes)
     * and return that response instead of the redirect response.
     *
     * @param int $limit
     *
     * @return self
     */
    public function withFollowingRedirects(int $limit = 10): self
    {
        $client = clone $this;
        $client->followRedirectsInterceptor = new FollowRedirects($limit);
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
        $client->followRedirectsInterceptor = null;
        return $client;
    }
}
