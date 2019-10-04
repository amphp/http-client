<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestException;

final class UnprocessedRequestException extends RequestException
{
    public function __construct(Request $request, HttpException $previous)
    {
        parent::__construct($request, "The request was not processed and can be safely retried", 0, $previous);
    }
}
