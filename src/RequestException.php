<?php

namespace Amp\Http\Client;

abstract class RequestException extends HttpException
{
    /** @var Request */
    private $request;

    public function __construct(Request $request, string $message, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->request = $request;
    }

    final public function getRequest(): Request
    {
        return $this->request;
    }
}
