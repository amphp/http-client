<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Response;

class TooManyRedirectsException extends HttpException
{
    /** @var Response */
    private $response;

    public function __construct(Response $response)
    {
        parent::__construct("There were too many redirects");

        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
