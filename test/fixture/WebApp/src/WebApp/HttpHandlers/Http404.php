<?php

namespace WebApp\HttpHandlers;

class Http404 {
    public function __invoke($request, $response, $e) {
        $response->setBody('not found');
    }
}
