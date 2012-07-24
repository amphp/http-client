<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class PluginAutoStatus {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
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
