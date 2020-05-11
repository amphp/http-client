<?php

declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Psr\Http\Message\StreamInterface;

use function Amp\Promise\timeout;
use function Amp\Promise\wait;

/**
 * @internal
 */
final class PsrRequestStream implements StreamInterface
{
    private const DEFAULT_TIMEOUT = 5000;

    /**
     * @var InputStream
     */
    private $stream;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @var bool
     */
    private $isEof = false;

    public function __construct(InputStream $stream, int $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->stream = $stream;
        $this->timeout = $timeout;
    }

    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        unset($this->stream);
    }

    public function eof(): bool
    {
        return $this->isEof;
    }

    public function tell()
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    /**
     * @return null
     */
    public function getSize()
    {
        return null;
    }

    /**
     * @return bool
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return void
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    /**
     * @return void
     */
    public function rewind()
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    /**
     * @return bool
     */
    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
        throw new \RuntimeException("Source stream is not writable");
    }

    /**
     * @param string|null $key
     * @return array|null
     */
    public function getMetadata($key = null)
    {
        return isset($key) ? null : [];
    }

    /**
     * @return null
     */
    public function detach()
    {
        unset($this->stream);

        return null;
    }

    /**
     * @return bool
     */
    public function isReadable()
    {
        return isset($this->stream);
    }

    /**
     * @param int $length
     * @return string
     */
    public function read($length)
    {
        while (!$this->isEof && \strlen($this->buffer) < $length) {
            $this->buffer .= $this->readFromStream();
        }

        $data = \substr($this->buffer, 0, $length);
        $this->buffer = \substr($this->buffer, \strlen($data));

        return $data;
    }

    private function readFromStream(): string
    {
        $data = wait(timeout($this->getOpenStream()->read(), $this->timeout));
        if (!isset($data)) {
            $this->isEof = true;

            return '';
        }
        if (\is_string($data)) {
            return $data;
        }

        throw new \RuntimeException("Invalid data received from stream");
    }

    private function getOpenStream(): InputStream
    {
        if (isset($this->stream)) {
            return $this->stream;
        }

        throw new \RuntimeException("Stream is closed");
    }

    public function getContents()
    {
        while (!$this->isEof) {
            $this->buffer .= $this->readFromStream();
        }

        return $this->buffer;
    }
}
