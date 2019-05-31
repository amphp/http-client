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
}
