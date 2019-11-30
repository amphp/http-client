<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;

final class Http2StreamException extends HttpException
{
    /** @var int */
    private $streamId;

    public function __construct(string $message, int $streamId, int $code, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->streamId = $streamId;
    }

    public function getStreamId(): int
    {
        return $this->streamId;
    }
}
