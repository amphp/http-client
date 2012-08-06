<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class Index {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
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
        /*
        $stream = $this->response->getBodyStream();
        $meta = stream_get_meta_data($stream);
        var_dump($meta);exit;
        */
        $this->response->setBody($body);
        $this->response->setStatusCode(200);
        $this->response->setStatusDescription('OK');
        $this->response->send();
        
        return $this->response;
    }

}
