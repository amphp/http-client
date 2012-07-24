<?php

namespace WebApp\Resources;

use Exception,
    Artax\Http\Request,
    Artax\Http\Response;


class ExceptionTest {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        throw new Exception('ExceptionTest::get');
    }

}
