<?php

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableStream;

interface RequestBody
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
