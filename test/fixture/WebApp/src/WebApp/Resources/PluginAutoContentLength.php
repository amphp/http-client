<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response;


class PluginAutoContentLength {
    
    private $request;
    private $response;
    
    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        $body = 'PluginAutoContentLength::get';
        $this->response->setBody($body);
        $this->response->send();
        
        return $this->response;
    }

}
