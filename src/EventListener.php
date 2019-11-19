<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\Stream;
use Amp\Promise;

interface EventListener
{
    public function startRequest(Request $request): Promise;

    public function startDnsResolution(Request $request): Promise;

    public function startConnectionAttempt(Request $request): Promise;

    public function startTlsNegotiation(Request $request): Promise;

    public function startSendingRequest(Request $request, Stream $stream): Promise;

    public function startWaitingForResponse(Request $request, Stream $stream): Promise;

    public function startReceivingResponse(Request $request, Stream $stream): Promise;

    public function completeRequest(Request $request): Promise;
}
