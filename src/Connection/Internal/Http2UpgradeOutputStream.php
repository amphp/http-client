<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpException;
use Amp\Promise;
use function Amp\call;

class Http2UpgradeOutputStream implements OutputStream
{
    private $write;

    public function __construct(callable $write)
    {
        $this->write = $write;
    }

    public function write(string $data): Promise
    {
        return $this->send($data, false);
    }

    public function end(string $finalData = ""): Promise
    {
        return $this->send($finalData, true);
    }

    private function send(string $data, bool $final): Promise
    {
        return call(function () use ($data, $final): \Generator {
            if ($this->write === null) {
                throw new ClosedException('The socket is no longer writable');
            }

            try {
                return yield call($this->write, $data, $final);
            } catch (HttpException $exception) {
                throw new StreamException('An error occurred while writing to the tunnelled socket', 0, $exception);
            }
        });
    }
}
