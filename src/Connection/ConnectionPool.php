<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;
use Amp\Promise;

interface ConnectionPool
{
    /**
     * Reserve a stream for a particular request.
     *
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Promise<Stream>
     */
    public function getStream(Request $request, CancellationToken $cancellation): Promise;
}
