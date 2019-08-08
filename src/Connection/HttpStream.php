<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

final class HttpStream implements Stream
{
    /** @var Connection */
    private $connection;

    /** @var callable */
    private $request;

    /** @var bool */
    private $used = false;

    public function __construct(Connection $connection, callable $request)
    {
        $this->connection = $connection;
        $this->request = $request;
    }

    public function request(Request $request, CancellationToken $token): Promise
    {
        if ($this->used) {
            throw new \Error('A stream may only be used for a single request');
        }

        $this->used = true;

        return ($this->request)($request, $token);
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->connection->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->connection->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->connection->getTlsInfo();
    }
}
