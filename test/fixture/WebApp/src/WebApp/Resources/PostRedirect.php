<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


class PostRedirect {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function post() {
        $this->response->setStatusCode(301);
        $this->response->setRawHeader('Location: /post-only');
        $this->response->send();
        
        return $this->response;
    }
}
