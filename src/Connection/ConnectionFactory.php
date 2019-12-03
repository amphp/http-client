<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;
use Amp\Promise;

interface ConnectionFactory
{
    /**
     * During connection establishment, the factory must call the {@see EventListener::startConnectionCreation()},
     * {@see EventListener::startTlsNegotiation()}, {@see EventListener::completeTlsNegotiation()}, and
     * {@see EventListener::completeConnectionCreation()} on all event listeners registered on the given request in the
     * order defined by {@see Request::getEventListeners()} as appropriate (TLS events are only invoked if TLS is
     * used). Before calling the next listener, the promise returned from the previous one must resolve successfully.
     *
     * Additionally, the factory may invoke {@see EventListener::startDnsResolution()} and
     * {@see EventListener::completeDnsResolution()}, but is not required to implement such granular events.
     *
     * @param Request           $request
     * @param CancellationToken $cancellationToken
     *
     * @return Promise
     */
    public function create(Request $request, CancellationToken $cancellationToken): Promise;
}
