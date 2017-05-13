<?php

namespace Amp\Artax\Cookie;

use Amp\Artax\HttpException;

class CookieFormatException extends HttpException {
    public function __construct(string $cookieString, string $reason = "") {
        parent::__construct("Invalid cookie string: '{$cookieString}', reason: '{$reason}'", 0, null);
    }
}
