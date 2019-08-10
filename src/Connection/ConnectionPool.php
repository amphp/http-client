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
     * @return Promise<Stream>
     */
    public function getStream(Request $request, CancellationToken $token): Promise;

    /**
     * @return string[] Array of supported protocol versions.
     */
    public function getProtocolVersions(): array;
}
