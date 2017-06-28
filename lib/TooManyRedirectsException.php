<?php

namespace Amp\Artax;

class TooManyRedirectsException extends HttpException {
    private $response;

    public function __construct(Response $response) {
        parent::__construct("There were too many redirects");

        $this->response = $response;
    }

    public function getResponse(): Response {
        return $this->response;
    }
}
