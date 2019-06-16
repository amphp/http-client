<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * Interface definition for an HTTP client.
 */
interface Client
{
    /**
     * Asynchronously request an HTTP resource.
     *
     * @param Request           $request A Request instance.
     * @param CancellationToken $cancellation A cancellation token to optionally cancel requests.
     *
     * @return Promise A promise to resolve to a response object as soon as its headers are received.
     */
    public function request(
        Request $request,
        CancellationToken $cancellation = null
    ): Promise;

    /**
     * Adds a network interceptor.
     *
     * Whether the given network interceptor will be respected for currently running requests is undefined.
     *
     * Any new requests have to take the new interceptor into account.
     *
     * @param NetworkInterceptor $networkInterceptor
     */
    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void;
}
