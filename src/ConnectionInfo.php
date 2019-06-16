<?php

namespace Amp\Http\Client;

use Amp\Socket\EncryptableSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

final class ConnectionInfo
{
    private $localAddress;
    private $remoteAddress;
    private $tlsInfo;

    public static function fromSocket(Socket $socket): self
    {
        return new self(
            $socket->getLocalAddress(),
            $socket->getRemoteAddress(),
            $socket instanceof EncryptableSocket ? $socket->getTlsInfo() : null
        );
    }

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
