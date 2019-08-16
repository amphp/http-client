<?php

namespace Amp\Http\Client\Connection;

final class Http2ConnectionException extends Http2Exception
{
    public function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
