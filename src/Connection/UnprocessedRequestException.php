<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

final class UnprocessedRequestException extends HttpException
{
    /** @var Request */
    private $request;

    public function __construct(Request $request, HttpException $previous)
    {
        parent::__construct("The request was not processed and can be safely retried", 0, $previous);
        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return clone $this->request;
    }
}
