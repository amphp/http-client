<?php

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;

final class UpgradedSocket implements EncryptableSocket
{
    use ForbidCloning;
    use ForbidSerialization;

    private EncryptableSocket $socket;

    private ?string $buffer;

    /**
     * @param string $buffer Remaining buffer previously read from the socket.
     */
    public function __construct(EncryptableSocket $socket, string $buffer)
    {
        $this->socket = $socket;
        $this->buffer = $buffer !== '' ? $buffer : null;
    }

    public function read(?Cancellation $cancellation = null, ?int $limit = null): ?string
    {
        if ($this->buffer !== null) {
            if ($limit !== null && $limit < \strlen($this->buffer)) {
                $buffer = \substr($this->buffer, 0, $limit);
                $this->buffer = \substr($this->buffer, $limit);

                return $buffer;
            }

            $buffer = $this->buffer;
            $this->buffer = null;

            return $buffer;
        }

        return $this->socket->read($cancellation);
    }

    public function close(): void
    {
        $this->socket->close();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function write(string $bytes): void
    {
        $this->socket->write($bytes);
    }

    public function end(): void
    {
        $this->socket->end();
    }

    public function reference(): void
    {
        $this->socket->reference();
    }

    public function unreference(): void
    {
        $this->socket->unreference();
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->socket->onClose($onClose);
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function setupTls(?Cancellation $cancellation = null): void
    {
        $this->socket->setupTls($cancellation);
    }

    public function shutdownTls(?Cancellation $cancellation = null): void
    {
        $this->socket->shutdownTls();
    }

    public function isTlsAvailable(): bool
    {
        return $this->socket->isTlsAvailable();
    }

    public function getTlsState(): TlsState
    {
        return $this->socket->getTlsState();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket->getTlsInfo();
    }

    public function isReadable(): bool
    {
        return $this->socket->isReadable();
    }

    public function isWritable(): bool
    {
        return $this->socket->isWritable();
    }

    public function getResource()
    {
        return $this->socket->getResource();
    }
}
