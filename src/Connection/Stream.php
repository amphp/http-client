<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Stream extends DelegateHttpClient
{
    /**
     * @param Request           $request
     * @param CancellationToken $token
     *
     * @return Promise
     *
     * @throws \Error Thrown if this method is called more than once.
     */
    public function request(Request $request, CancellationToken $token): Promise;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
