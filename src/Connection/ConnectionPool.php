<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\Http\Client\Request;

interface ConnectionPool
{
    /**
     * Reserve a stream for a particular request.
     */
    public function getStream(Request $request, Cancellation $cancellation): Stream;
}
