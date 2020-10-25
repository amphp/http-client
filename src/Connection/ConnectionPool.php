<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;

interface ConnectionPool
{
    /**
     * Reserve a stream for a particular request.
     *
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Stream
     */
    public function getStream(Request $request, CancellationToken $cancellation): Stream;
}
