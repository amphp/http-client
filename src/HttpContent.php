<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableStream;

interface HttpContent
{
    /**
     * @throws HttpException
     */
    public function getContent(): ReadableStream;

    /**
     * @throws HttpException
     */
    public function getContentLength(): ?int;

    /**
     * @throws HttpException
     */
    public function getContentType(): string;
}
