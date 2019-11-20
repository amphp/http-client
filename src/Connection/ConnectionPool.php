<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\Request;
use Amp\Promise;

interface ConnectionPool
{
    /**
     * Reserve a stream for a particular request.
     *
     * During connection establishment, the pool must call the {@see EventListener::startConnectionCreation()},
     * {@see EventListener::startTlsNegotiation()}, and {@see EventListener::completeTlsNegotiation()} on all event
     * listeners registered on the given request in the order defined by {@see Request::getEventListeners()} as
     * appropriate. Before calling the next listener, the promise returned from the previous one must resolve
     * successfully.
     *
     * Additionally, the pool may invoke {@see EventListener::startDnsResolution()} and
     * {@see EventListener::completeDnsResolution()}, but is not required to implement such granular events.
     *
     * @param Request           $request
     * @param CancellationToken $token
     *
     * @return Promise<Stream>
     */
    public function getStream(Request $request, CancellationToken $token): Promise;
}
