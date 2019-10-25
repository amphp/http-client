<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Interceptor\FollowRedirects;
use Amp\Http\Client\Interceptor\RetryRequests;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;

final class HttpClientBuilder
{
    use ForbidCloning;
    use ForbidSerialization;

    public static function buildDefault(): HttpClient
    {
        return (new self)->build();
    }

    /** @var RetryRequests|null */
    private $retryInterceptor;
    /** @var FollowRedirects|null */
    private $followRedirectsInterceptor;
    /** @var ApplicationInterceptor[] */
    private $applicationInterceptors = [];
    /** @var NetworkInterceptor[] */
    private $networkInterceptors = [];
    /** @var ConnectionPool */
    private $pool;

    public function __construct()
    {
        $this->followRedirectsInterceptor = new FollowRedirects(10);
        $this->retryInterceptor = new RetryRequests(2);
    }

    public function build(): HttpClient
    {
        $client = new PooledHttpClient($this->pool);

        foreach ($this->networkInterceptors as $interceptor) {
            $client = $client->intercept($interceptor);
        }

        foreach (\array_reverse($this->applicationInterceptors) as $interceptor) {
            $client = new InterceptedHttpClient($client, $interceptor);
        }

        if ($this->followRedirectsInterceptor) {
            $client = new InterceptedHttpClient($client, $this->followRedirectsInterceptor);
        }

        if ($this->retryInterceptor) {
            $client = new InterceptedHttpClient($client, $this->retryInterceptor);
        }

        return $client;
    }

    /**
     * @param ConnectionPool $pool Connection pool to use.
     *
     * @return self
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
     *
     * @return self
     */
    public function intercept(ApplicationInterceptor $interceptor): self
    {
        $builder = clone $this;
        $builder->applicationInterceptors[] = $interceptor;

        return $builder;
    }

    /**
     * @param NetworkInterceptor $interceptor This interceptor gets added to the interceptor queue, so interceptors
     *                                        are executed in the order given to this method.
     *
     * @return self
     */
    public function interceptNetwork(NetworkInterceptor $interceptor): self
    {
        $builder = clone $this;
        $builder->networkInterceptors[] = $interceptor;

        return $builder;
    }

    /**
     * @param int $retryLimit Maximum number of times a request may be retried. Only certain requests will be retried
     *                        automatically (GET, HEAD, PUT, and DELETE requests are automatically retried, or any
     *                        request that was indicated as unprocessed by the connection).
     *
     * @return self
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
     *
     * @return self
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
}
