<?php

namespace WebApp\Resources;

use Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse;


class AsyncTest {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    public function get() {
        $file = '/home/daniel/dev/Artax/async-test.log';
        $fh = fopen($file, 'a+');
        fwrite($fh, date('r'));
        fclose($fh);
        
        $body = '<h1>Async Test</h1>';
        
        $this->response->setBody($body);
        $this->response->setStatusCode(201);
        $this->response->setStatusDescription('Created');
        $this->response->send();
        
        return $this->response;
    }
}
