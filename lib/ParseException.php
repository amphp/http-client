<?php

namespace Amp\Http\Client;

class ParseException extends HttpException
{
    /**
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previousException
     */
    public function __construct(string $message, int $code, \Throwable $previousException = null)
    {
        parent::__construct($message, $code, $previousException);
    }
}
