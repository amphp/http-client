<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Connection
{
    /**
     * @param Request                $request
     * @param CancellationToken|null $token
     *
     * @return Promise<Response>
     */
    public function request(Request $request, ?CancellationToken $token = null): Promise;

    public function isBusy(): bool;

    public function isClosed(): bool;

    public function close(): Promise;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
