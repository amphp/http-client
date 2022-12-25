<?php declare(strict_types=1);

namespace Amp\Http\Client\Body;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\RequestBody;

final class StreamBody implements RequestBody
{
    private ?ReadableStream $stream;
    private array $headers;
    private ?int $length;

    public function __construct(ReadableStream $stream, array $headers = [], ?int $length = null)
    {
        $this->stream = $stream;
        $this->headers = $headers;
        $this->length = $length;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function createBodyStream(): ReadableStream
    {
        if ($this->stream === null) {
            throw new HttpException('Unable to repeatedly stream request body');
        }

        try {
            return $this->stream;
        } finally {
            $this->stream = null;
        }
    }

    public function getBodyLength(): ?int
    {
        return $this->length;
    }
}
