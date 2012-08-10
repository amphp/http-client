<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


class Diagnostics {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        echo '<pre>';
        var_dump($this->request);
        print_r($_SERVER);
        echo '</pre>';
        
        die;
    }

}
