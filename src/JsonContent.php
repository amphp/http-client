<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;

final class JsonContent implements HttpContent
{
    private readonly string $data;

    /**
     * @param mixed $json Data which may be JSON serialized with {@see json_encode()}.
     */
    public function __construct(mixed $json)
    {
        try {
            $this->data = \json_encode($json, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new HttpException(
                'Exception thrown encoding JSON content: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    public function getContent(): ReadableStream
    {
        return new ReadableBuffer($this->data);
    }

    public function getContentLength(): int
    {
        return \strlen($this->data);
    }

    public function getContentType(): string
    {
        return 'application/json';
    }
}
