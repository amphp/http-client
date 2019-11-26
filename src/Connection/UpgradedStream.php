<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\Failure;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Promise;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use function Amp\call;

final class UpgradedStream implements Socket
{
    use ForbidCloning;
    use ForbidSerialization;

    private $read;
    private $write;
    private $localAddress;
    private $remoteAddress;

    public function __construct(InputStream $read, OutputStream $write, SocketAddress $local, SocketAddress $remote)
    {
        $this->read = $read;
        $this->write = $write;
        $this->localAddress = $local;
        $this->remoteAddress = $remote;
    }

    public function read(): Promise
    {
        return call(function (): \Generator {
            if ($this->read === null) {
                throw new SocketException('The socket is no longer readable');
            }

            $chunk = yield $this->read->read();

            if ($chunk === null) {
                $this->read = null;
            }

            return $chunk;
        });
    }

    public function close(): void
    {
        if ($this->write !== null) {
            $this->write->end();
        }

        $this->read = null;
        $this->write = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function write(string $data): Promise
    {
        return call(function () use ($data): \Generator {
            if ($this->write === null) {
                throw new SocketException('The socket is no longer writable');
            }

            try {
                return yield $this->write->write($data);
            } catch (\Throwable $exception) {
                $this->write = null;
                throw $exception;
            }
        });
    }

    public function end(string $finalData = ""): Promise
    {
        if ($this->write === null) {
            return new Failure(new SocketException('The socket is no longer writable'));
        }

        $promise = $this->write->end($finalData);
        $this->write = null;
        return $promise;
    }

    public function reference(): void
    {
        // No-op
    }

    public function unreference(): void
    {
        // No-op
    }

    public function isClosed(): bool
    {
        return $this->read === null;
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->remoteAddress;
    }
}
