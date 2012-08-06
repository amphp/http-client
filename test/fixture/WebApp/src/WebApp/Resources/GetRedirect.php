<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class GetRedirect {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        $this->response->setStatusCode(301);
        $this->response->setRawHeader('Location: /');
        $this->response->send();
        
        return $this->response;
    }
}
