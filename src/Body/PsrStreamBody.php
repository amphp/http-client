<?php

declare(strict_types=1);

namespace Amp\Http\Client\Body;

use Amp\ByteStream\InputStream;
use Amp\Http\Client\Internal\PsrInputStream;
use Amp\Http\Client\RequestBody;
use Amp\Promise;
use Amp\Success;
use Psr\Http\Message\StreamInterface;

final class PsrStreamBody implements RequestBody
{

    /**
     * @var StreamInterface
     */
    private $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function getBodyLength(): Promise
    {
        return new Success($this->stream->getSize() ?? -1);
    }

    public function getHeaders(): Promise
    {
        return new Success([]);
    }

    public function createBodyStream(): InputStream
    {
        return new PsrInputStream($this->stream);
    }
}
