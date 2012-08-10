<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


class PluginAutoStatus {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        $body = 'PluginAutoStatus::get';
        $this->response->setBody($body);
        $this->response->send();
        
        return $this->response;
    }

}
