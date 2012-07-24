<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class PluginAutoContentLength {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
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
