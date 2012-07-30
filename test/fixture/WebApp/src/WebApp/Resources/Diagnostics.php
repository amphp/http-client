<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class Diagnostics {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        echo '<pre>'; var_dump($this->request); echo '</pre>'; die;
    }

}
