<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Stream extends DelegateHttpClient
{
    /**
     * Executes the request.
     *
     * This method may only be invoked once per instance.
     *
     * The stream must call {@see EventListener::startSendingRequest()},
     * {@see EventListener::completeSendingRequest()}, {@see EventListener::startReceivingResponse()}, and
     * {@see EventListener::completeReceivingResponse()} event listener methods on all event listeners registered on
     * the given request in the order defined by {@see Request::getEventListeners()}. Before calling the next listener,
     * the promise returned from the previous one must resolve successfully.
     *
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Promise<Response>
     *
     * @throws \Error Thrown if this method is called more than once.
     */
    public function request(Request $request, CancellationToken $cancellation): Promise;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
