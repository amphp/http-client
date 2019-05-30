<?php

namespace Amp\Http\Client\Cookie;

use Amp\Http\Client\HttpException;

class CookieFormatException extends HttpException
{
    public function __construct(string $cookieString, string $reason = "")
    {
        parent::__construct("Invalid cookie string: '{$cookieString}', reason: '{$reason}'");
    }
}
