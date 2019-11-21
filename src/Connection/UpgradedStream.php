<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InputStream;
use Amp\Failure;
use Amp\Http\Client\HttpException;
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

    public function __construct(InputStream $read, callable $write, SocketAddress $local, SocketAddress $remote)
    {
        $this->read = $read;
        $this->write = $write;
        $this->localAddress = $local;
        $this->remoteAddress = $remote;
    }

    public function read(): Promise
    {
        if ($this->read === null) {
            return new Failure(new SocketException('The socket is no longer readable'));
        }

        return $this->read->read();
    }

    public function close(): void
    {
        if ($this->write !== null) {
            ($this->write)('', true);
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
        return $this->send($data, false);
    }


    public function send(string $data, bool $final): Promise
    {
        return call(function () use ($data, $final): \Generator {
            if ($this->write === null) {
                throw new SocketException('The socket is no longer writable');
            }

            try {
                return yield call($this->write, $data, $final);
            } catch (HttpException $exception) {
                throw new SocketException('An error occurred while writing to the tunnelled socket', 0, $exception);
            }
        });
    }

    public function end(string $finalData = ""): Promise
    {
        return $this->send($finalData, true);
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
