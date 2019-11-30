<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;

final class Http2ConnectionException extends HttpException
{
    public function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
