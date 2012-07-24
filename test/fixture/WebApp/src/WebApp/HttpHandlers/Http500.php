<?php

namespace WebApp\HttpHandlers;

use ErrorException,
    Artax\Framework\FatalErrorException;

class Http500 {
    public function __invoke($request, $response, $e, $debugMode) {
        if ($e instanceof FatalErrorException) {
            $response->setBody('fatal');
        } elseif ($e instanceof ErrorException) {
            $response->setBody('error');
        } else {
            $response->setBody('exception');
        }
    }
}
