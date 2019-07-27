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
    public const MAX_KEEP_ALIVE_TIMEOUT = 60;

    /**
     * @param Request           $request
     * @param CancellationToken $token
     *
     * @return Promise<Response>
     */
    public function request(Request $request, CancellationToken $token): Promise;

    public function isBusy(): bool;

    public function close(): Promise;

    public function onClose(callable $onClose): void;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
