<?php

namespace Amp\Http\Client;

final class ConnectionInfo
{
    private $localAddress;
    private $remoteAddress;
    private $tlsInfo;

    public function __construct(string $localAddress, string $remoteAddress, ?TlsInfo $tlsInfo = null)
    {
        $this->localAddress = $localAddress;
        $this->remoteAddress = $remoteAddress;
        $this->tlsInfo = $tlsInfo;
    }

    public function getLocalAddress(): string
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->tlsInfo;
    }
}
