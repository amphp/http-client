<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\Request;
use Amp\Socket\Socket;

interface ConnectionPool
{
    /**
     * @param Socket  $socket
     *
     * @return Connection
     */
    public function createConnection(Socket $socket): Connection;

    /**
     * @param Request $request
     *
     * @return Connection|null
     */
    public function getConnection(Request $request): ?Connection;

    /**
     * @return string[] A list of supported application-layer protocols (ALPNs).
     */
    public function getApplicationLayerProtocols(): array;
}
