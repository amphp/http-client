<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ForbidSerialization;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\FollowRedirects;
use Amp\Http\Client\Interceptor\ForbidUriUserInfo;
use Amp\Http\Client\Interceptor\RetryRequests;
use Amp\Http\Client\Interceptor\SetRequestHeaderIfUnset;

/**
 * Allows building an HttpClient instance.
 *
 * The builder is the recommended way to build an HttpClient instance.
 */
final class HttpClientBuilder
{
    use ForbidSerialization;

    public static function buildDefault(): HttpClient
    {
        return (new self)->build();
    }

    private ?ForbidUriUserInfo $forbidUriUserInfo;

    private ?RetryRequests $retryInterceptor;

    private ?FollowRedirects $followRedirectsInterceptor;

    private ?SetRequestHeaderIfUnset $defaultUserAgentInterceptor;

    private ?SetRequestHeaderIfUnset $defaultAcceptInterceptor;

    private ?DecompressResponse $defaultCompressionHandler;

    /** @var ApplicationInterceptor[] */
    private array $applicationInterceptors = [];

    /** @var NetworkInterceptor[] */
    private array $networkInterceptors = [];

    /** @var EventListener[] */
    private array $eventListeners = [];

    private ConnectionPool $pool;

    public function __construct()
    {
        $this->pool = new UnlimitedConnectionPool;
        $this->forbidUriUserInfo = new ForbidUriUserInfo;
        $this->followRedirectsInterceptor = new FollowRedirects(10);
        $this->retryInterceptor = new RetryRequests(2);
        $this->defaultAcceptInterceptor = new SetRequestHeaderIfUnset('accept', '*/*');
        $this->defaultUserAgentInterceptor = new SetRequestHeaderIfUnset('user-agent', 'amphp/http-client @ v5.x');
        $this->defaultCompressionHandler = new DecompressResponse;
    }

    public function build(): HttpClient
    {
        $client = new PooledHttpClient($this->pool);

        foreach ($this->networkInterceptors as $interceptor) {
            $client = $client->intercept($interceptor);
        }

        if ($this->defaultAcceptInterceptor) {
            $client = $client->intercept($this->defaultAcceptInterceptor);
        }

        if ($this->defaultUserAgentInterceptor) {
            $client = $client->intercept($this->defaultUserAgentInterceptor);
        }

        if ($this->defaultCompressionHandler) {
            $client = $client->intercept($this->defaultCompressionHandler);
        }

        foreach ($this->eventListeners as $eventListener) {
            $client = $client->listen($eventListener);
        }

        $applicationInterceptors = $this->applicationInterceptors;

        if ($this->followRedirectsInterceptor) {
            \array_unshift($applicationInterceptors, $this->followRedirectsInterceptor);
        }

        if ($this->forbidUriUserInfo) {
            \array_unshift($applicationInterceptors, $this->forbidUriUserInfo);
        }

        if ($this->retryInterceptor) {
            $applicationInterceptors[] = $this->retryInterceptor;
        }

        foreach (\array_reverse($applicationInterceptors) as $applicationInterceptor) {
            $client = new InterceptedHttpClient($client, $applicationInterceptor, $this->eventListeners);
        }

        return new HttpClient($client, $this->eventListeners);
    }

    /**
     * @param ConnectionPool $pool Connection pool to use.
     */
    public function usingPool(ConnectionPool $pool): self
    {
        $builder = clone $this;
        $builder->pool = $pool;

        return $builder;
    }

    /**
     * @param ApplicationInterceptor $interceptor This interceptor gets added to the interceptor queue, so interceptors
     *                                            are executed in the order given to this method.
     */
    public function intercept(ApplicationInterceptor $interceptor): self
    {
        if ($this->followRedirectsInterceptor !== null && $interceptor instanceof FollowRedirects) {
            throw new \Error('Disable automatic redirect following or use HttpClientBuilder::followRedirects() to customize redirects');
        }

        if ($this->retryInterceptor !== null && $interceptor instanceof RetryRequests) {
            throw new \Error('Disable automatic retries or use HttpClientBuilder::retry() to customize retries');
        }

        $builder = clone $this;
        $builder->applicationInterceptors[] = $interceptor;

        return $builder;
    }

    /**
     * @param NetworkInterceptor $interceptor This interceptor gets added to the interceptor queue, so interceptors
     *                                        are executed in the order given to this method.
     */
    public function interceptNetwork(NetworkInterceptor $interceptor): self
    {
        $builder = clone $this;
        $builder->networkInterceptors[] = $interceptor;

        return $builder;
    }

    /**
     * @param EventListener $eventListener This event listener gets added to the request automatically.
     */
    public function listen(EventListener $eventListener): self
    {
        $builder = clone $this;
        $builder->eventListeners[] = $eventListener;

        return $builder;
    }

    /**
     * @param int $retryLimit Maximum number of times a request may be retried. Only certain requests will be retried
     *                        automatically (GET, HEAD, PUT, and DELETE requests are automatically retried, or any
     *                        request that was indicated as unprocessed by the connection).
     */
    public function retry(int $retryLimit): self
    {
        $builder = clone $this;

        if ($retryLimit <= 0) {
            $builder->retryInterceptor = null;
        } else {
            $builder->retryInterceptor = new RetryRequests($retryLimit);
        }

        return $builder;
    }

    /**
     * @param int $limit Maximum number of redirects to follow. The client will automatically request the URI supplied
     *                   by a redirect response (3xx status codes) and returns that response instead.
     */
    public function followRedirects(int $limit = 10): self
    {
        $builder = clone $this;

        if ($limit <= 0) {
            $builder->followRedirectsInterceptor = null;
        } else {
            $builder->followRedirectsInterceptor = new FollowRedirects($limit);
        }

        return $builder;
    }

    /**
     * Removes the default restriction of user:password in request URIs.
     */
    public function allowDeprecatedUriUserInfo(): self
    {
        $builder = clone $this;
        $builder->forbidUriUserInfo = null;

        return $builder;
    }

    /**
     * Doesn't automatically set an 'accept' header.
     */
    public function skipDefaultAcceptHeader(): self
    {
        $builder = clone $this;
        $builder->defaultAcceptInterceptor = null;

        return $builder;
    }

    /**
     * Doesn't automatically set a 'user-agent' header.
     */
    public function skipDefaultUserAgent(): self
    {
        $builder = clone $this;
        $builder->defaultUserAgentInterceptor = null;

        return $builder;
    }

    /**
     * Doesn't automatically set an 'accept-encoding' header and decompress the response.
     */
    public function skipAutomaticCompression(): self
    {
        $builder = clone $this;
        $builder->defaultCompressionHandler = null;

        return $builder;
    }

    final protected function __clone()
    {
        // clone is automatically denied to all external calls
        // final protected instead of private to also force denial for all children
    }
}
