<?php declare(strict_types=1);

namespace Amp\Http\Client;

final class ParseException extends HttpException
{
    public function __construct(string $message, int $code, \Throwable $previousException = null)
    {
        parent::__construct($message, $code, $previousException);
    }
}
