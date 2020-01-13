<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;

/**
 * @deprecated Exception moved to amphp/http. Catch the base exception class (HttpException) instead.
 */
final class Http2ConnectionException extends HttpException
{
    public function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
