<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\Request;
use Amp\Socket\Socket;

final class DefaultConnectionPool implements ConnectionPool
{
    private $connections = [];

    public function createConnection(Socket $socket): Connection
    {
        $connection = new Http1Connection($socket);
        $this->connections[$socket->getRemoteAddress()->toString()] = $connection;
        return $connection;
    }

    public function getConnection(Request $request): ?Connection
    {
        $uri = $request->getUri();

        $address = $uri->getHost();
        $port = $uri->getPort();

        if ($port !== null) {
            $address .= ':' . $port;
        }

        if (isset($this->connections[$address])) {
            return $this->connections[$address];
        }

        return null;
    }

    public function getApplicationLayerProtocols(): array
    {
        return ['http/1.1'];
    }
}
