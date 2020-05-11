<?php

declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Success;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrInputStream implements InputStream
{
    private const DEFAULT_CHUNK_SIZE = 8192;

    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @var bool
     */
    private $tryRewind = true;

    public function __construct(StreamInterface $stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        if ($chunkSize < 1) {
            throw new \Error("Invalid chunk size: {$chunkSize}");
        }
        $this->stream = $stream;
        $this->chunkSize = $chunkSize;
    }

    public function read(): Promise
    {
        if (!$this->stream->isReadable()) {
            return new Success();
        }
        if ($this->tryRewind) {
            $this->tryRewind = false;
            if ($this->stream->isSeekable()) {
                $this->stream->rewind();
            }
        }
        if ($this->stream->eof()) {
            return new Success();
        }

        $data = $this->stream->read($this->chunkSize);

        return new Success($data);
    }
}
