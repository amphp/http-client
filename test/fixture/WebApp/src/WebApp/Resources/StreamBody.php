<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


class StreamBody {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function post() {
        $entityBodyStream = $this->request->getBodyStream();
        
        $this->response->setBody($entityBodyStream);
        $this->response->setStatusCode(200);
        $this->response->setStatusDescription('OK');
        $this->response->send();
        
        return $this->response;
    }
}
