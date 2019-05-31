<?php

namespace Amp\Http\Client;

use Amp\Socket\SocketAddress;

final class ConnectionInfo
{
    private $localAddress;
    private $remoteAddress;
    private $tlsInfo;

    public function __construct(SocketAddress $localAddress, SocketAddress $remoteAddress, ?TlsInfo $tlsInfo = null)
    {
        $this->localAddress = $localAddress;
        $this->remoteAddress = $remoteAddress;
        $this->tlsInfo = $tlsInfo;
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->remoteAddress;
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->tlsInfo;
    }
}
