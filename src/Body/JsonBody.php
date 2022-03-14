<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\Http\Client\Body;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\RequestBody;

final class JsonBody implements RequestBody
{
    private string $json;

    /**
     * JsonBody constructor.
     *
     * @throws HttpException
     */
    public function __construct($data, int $options = 0, int $depth = 512)
    {
        $this->json = \json_encode($data, $options, $depth);

        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new HttpException('Failed to encode data to JSON');
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
