<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\Internal\InterceptedConnection;
use Amp\NullCancellationToken;
use Amp\Promise;
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
     * @param Request           $request A Request instance.
     * @param CancellationToken $cancellation A cancellation token to optionally cancel requests.
     *
     * @return Promise A promise to resolve to a response object as soon as its headers are received.
     */
    public function request(Request $request, ?CancellationToken $cancellation = null): Promise
    {
        $cancellation = $cancellation ?? new NullCancellationToken;

        return call(function () use ($request, $cancellation) {
            if ($this->applicationInterceptors) {
                $client = clone $this;
                $interceptor = \array_shift($client->applicationInterceptors);
                $response = yield $interceptor->interceptApplicationRequest($request, $cancellation, $client);
            } else {
                $connection = yield $this->connectionPool->getConnection($request, $cancellation);
                \assert($connection instanceof Connection);

                if ($this->networkInterceptors) {
                    $connection = new InterceptedConnection($connection, ...$this->networkInterceptors);
                }

                $response = yield $connection->request($request, $cancellation);
            }

            return $response;
        });
    }

    /**
     * Adds a network interceptor.
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
