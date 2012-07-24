<?php

namespace WebApp\Resources;

use Exception,
    Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class ExceptionTest {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        throw new Exception('ExceptionTest::get');
    }

}
