<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\Http\Client\Request;
use function Amp\Http\Client\events;

interface ConnectionFactory
{
    /**
     * Creates a new connection.
     *
     * The implementation should call appropriate event handlers via {@see events()}.
     */
    public function create(Request $request, Cancellation $cancellation): Connection;
}
