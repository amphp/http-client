<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\Http\Client\Body;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\RequestBody;
use Amp\Promise;
use Amp\Success;

final class JsonBody implements RequestBody
{
    /** @var string */
    private $json;

    /**
     * JsonBody constructor.
     *
     * @param mixed $data
     * @param int   $options
     * @param int   $depth
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

    public function getHeaders(): Promise
    {
        return new Success(['content-type' => 'application/json; charset=utf-8']);
    }

    public function createBodyStream(): InputStream
    {
        return new InMemoryStream($this->json);
    }

    public function getBodyLength(): Promise
    {
        return new Success(\strlen($this->json));
    }
}
