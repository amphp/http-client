<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;;

class FatalError {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
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
