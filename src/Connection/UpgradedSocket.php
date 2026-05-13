<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\ResourceStream;
use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;

/**
 * @implements \IteratorAggregate<int, string>
 */
final class UpgradedSocket implements Socket, ResourceStream, \IteratorAggregate
{
    use ForbidCloning;
    use ForbidSerialization;
    use ReadableStreamIteratorAggregate;

    private ?string $buffer;

    /**
     * @param string $buffer Remaining buffer previously read from the socket.
     */
    public function __construct(private readonly Socket $socket, string $buffer)
    {
        $this->buffer = $buffer !== '' ? $buffer : null;
    }

    #[\Override]
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

    #[\Override]
    public function close(): void
    {
        $this->socket->close();
    }

    public function __destruct()
    {
        $this->close();
    }

    #[\Override]
    public function write(string $bytes): void
    {
        $this->socket->write($bytes);
    }

    #[\Override]
    public function end(): void
    {
        $this->socket->end();
    }

    #[\Override]
    public function reference(): void
    {
        if ($this->socket instanceof ResourceStream) {
            $this->socket->reference();
        }
    }

    #[\Override]
    public function unreference(): void
    {
        if ($this->socket instanceof ResourceStream) {
            $this->socket->unreference();
        }
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    #[\Override]
    public function onClose(\Closure $onClose): void
    {
        $this->socket->onClose($onClose);
    }

    #[\Override]
    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    #[\Override]
    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    #[\Override]
    public function setupTls(?Cancellation $cancellation = null): void
    {
        $this->socket->setupTls($cancellation);
    }

    #[\Override]
    public function shutdownTls(?Cancellation $cancellation = null): void
    {
        $this->socket->shutdownTls();
    }

    #[\Override]
    public function isTlsConfigurationAvailable(): bool
    {
        return $this->socket->isTlsConfigurationAvailable();
    }

    #[\Override]
    public function getTlsState(): TlsState
    {
        return $this->socket->getTlsState();
    }

    #[\Override]
    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket->getTlsInfo();
    }

    #[\Override]
    public function isReadable(): bool
    {
        return $this->socket->isReadable();
    }

    #[\Override]
    public function isWritable(): bool
    {
        return $this->socket->isWritable();
    }

    #[\Override]
    public function getResource()
    {
        return $this->socket instanceof ResourceStream
            ? $this->socket->getResource()
            : null;
    }
}
