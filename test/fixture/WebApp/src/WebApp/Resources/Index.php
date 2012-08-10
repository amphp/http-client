<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


class Index {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        $body = 'Index::get';
        $this->response->setBody($body);
        $this->response->setStatusCode(200);
        $this->response->setStatusDescription('OK');
        $this->response->send();
        
        return $this->response;
    }
    
    public function post() {
        $body = '<h1>!ZANZIBAR!</h1>';
        
        while ($data = fread($this->response->getBodyStream(), 8192)) {
            $body .= $data;
        }
        
        
        $this->response->setBody($body);
        $this->response->setStatusCode(200);
        $this->response->setStatusDescription('OK');
        $this->response->send();
        
        return $this->response;
    }

}
