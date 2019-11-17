<?php

namespace Amp\Http\Client\Tunnel\Internal;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

/** @internal */
final class TunnelSocket implements EncryptableSocket
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var EncryptableSocket */
    private $localSocket;
    /** @var EncryptableSocket */
    private $remoteSocket;

    public function __construct(EncryptableSocket $local, EncryptableSocket $remote)
    {
        $this->localSocket = $local;
        $this->remoteSocket = $remote;
    }

    public function setupTls(?CancellationToken $cancellationToken = null): Promise
    {
        return $this->localSocket->setupTls($cancellationToken);
    }

    public function shutdownTls(?CancellationToken $cancellationToken = null): Promise
    {
        return $this->localSocket->shutdownTls($cancellationToken);
    }

    public function getTlsState(): int
    {
        return $this->localSocket->getTlsState();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->localSocket->getTlsInfo();
    }

    public function read(): Promise
    {
        return $this->localSocket->read();
    }

    public function write(string $data): Promise
    {
        return $this->localSocket->write($data);
    }

    public function end(string $finalData = ""): Promise
    {
        return $this->localSocket->end($finalData);
    }

    public function reference(): void
    {
        $this->localSocket->reference();
        $this->remoteSocket->reference();
    }

    public function unreference(): void
    {
        $this->localSocket->unreference();
        $this->remoteSocket->unreference();
    }

    public function close(): void
    {
        // Don't close remote socket here, as there might still be pending data in flight there
        $this->localSocket->close();
    }

    public function isClosed(): bool
    {
        return $this->localSocket->isClosed();
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->localSocket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->remoteSocket->getRemoteAddress();
    }
}
