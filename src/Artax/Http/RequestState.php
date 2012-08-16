<?php

namespace Artax\Http;

class RequestState {
    
    public $status = Client::REQUEST_SENDING_HEADERS;
    public $conn;
    public $request;
    public $response;
    public $buffer = '';
    public $bufferSize = 0;
    public $redirectHistory = array();
    
    public $totalBytesSent = 0;
    public $totalBytesRead = 0;
    
    public $responseTotalBytes = 0;
    public $responseHeaderBytes = 0;
    public $responseBodyStream;
    
    public $requestBodyStreamPos = 0;
    public $requestBodyStreamBuffer = null;
    public $requestBodyStreamLength = 0;
    
    public function __toString() {
        return (string) $this->status;
    }
    
}
