<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\ConnectionInfo;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;

interface Connection
{
    /**
     * @param Request                $request
     * @param CancellationToken|null $token
     *
     * @return Promise<Response>
     */
    public function request(Request $request, ?CancellationToken $token = null): Promise;

    /**
     * @return ConnectionInfo
     */
    public function getConnectionInfo(): ConnectionInfo;

    public function isClosed(): bool;

    public function close(): Promise;
}
