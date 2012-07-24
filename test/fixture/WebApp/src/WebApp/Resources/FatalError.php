<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;

class FatalError {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        // cause a parse error
        }
        /*
        // cause a fatal "out of memory" error
        ini_set('memory_limit', '1M');
        $data = '';
        while(1) {
            $data .= str_repeat('#', PHP_INT_MAX);
        }
        */
    }

}
