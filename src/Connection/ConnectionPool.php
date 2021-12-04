<?php

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\Http\Client\Request;

interface ConnectionPool
{
    /**
     * Reserve a stream for a particular request.
     *
     * @param Request           $request
     * @param Cancellation $cancellation
     *
     * @return Stream
     */
    public function getStream(Request $request, Cancellation $cancellation): Stream;
}
