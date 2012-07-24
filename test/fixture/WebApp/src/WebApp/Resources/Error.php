<?php

namespace WebApp\Resources;

use Artax\Http\Request,
    Artax\Http\Response;


class Error {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        trigger_error('Error::get', E_USER_NOTICE);
    }

}
