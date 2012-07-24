<?php

namespace WebApp\Resources;

use Artax\Http\Request,
    Artax\Http\Response;

class FatalError {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        // cause a fatal error
        ini_set('memory_limit', '1M');
        $data = '';
        while(1) {
            $data .= str_repeat('#', PHP_INT_MAX);
        }
    }

}
