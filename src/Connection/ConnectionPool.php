<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;
use Amp\Promise;

interface ConnectionPool
{
    /**
     * @param Request           $request
     * @param CancellationToken $token
     *
     * @return Promise<Connection>
     */
    public function getConnection(Request $request, CancellationToken $token): Promise;
}
