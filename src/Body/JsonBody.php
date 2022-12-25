<?php declare(strict_types=1);
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\Http\Client\Body;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\RequestBody;

final class JsonBody implements RequestBody
{
    private string $json;

    /**
     * @throws HttpException
     */
    public function __construct(mixed $data, int $options = 0, int $depth = 512)
    {
        try {
            $this->json = \json_encode($data, $options | \JSON_THROW_ON_ERROR, $depth);
        } catch (\JsonException $exception) {
            throw new HttpException('Failed to encode data to JSON', 0, $exception);
        }
    }

    public function getHeaders(): array
    {
        return ['content-type' => 'application/json; charset=utf-8'];
    }

    public function createBodyStream(): ReadableStream
    {
        return new ReadableBuffer($this->json);
    }

    public function getBodyLength(): int
    {
        return \strlen($this->json);
    }
}
