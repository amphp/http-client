<?php

namespace WebApp\Resources;

use Exception,
    Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


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
