<?php

namespace WebApp\Resources;

use Artax\Http\Request,
    Artax\Http\Response;


class PostOnly {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function post() {
        $body = '<html><body><h1>PostOnly::post</h1></body></html>';
        $this->response->setBody($body);
        $this->response->setStatusCode(200);
        $this->response->setStatusDescription('OK');
        $this->response->send();
        
        return $this->response;
    }
}
