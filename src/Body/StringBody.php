<?php

namespace Amp\Http\Client\Body;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\RequestBody;

final class StringBody implements RequestBody
{
    private string $body;

    public function __construct(string $body)
    {
        $this->body = $body;
    }

    public function createBodyStream(): ReadableStream
    {
        return new ReadableBuffer($this->body !== '' ? $this->body : null);
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function getBodyLength(): int
    {
        return \strlen($this->body);
    }
}
