<?php

namespace WebApp\HttpHandlers;

class Http405 {
    public function __invoke($request, $response, $e) {
        $response->setBody('method not allowed');
    }
}
