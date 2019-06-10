<?php

namespace Amp\Http\Client\Driver;

use Amp\CancellationToken;
use Amp\Http\Client\ConnectionInfo;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Socket\Socket;

interface HttpDriver
{
    /**
     * @param Socket                 $socket
     * @param Request                $request
     * @param CancellationToken|null $token
     *
     * @return Promise<Response>
     */
    public function request(Socket $socket, ConnectionInfo $connectionInfo, Request $request, ?CancellationToken $token = null): Promise;
}
