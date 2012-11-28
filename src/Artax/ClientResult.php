<?php

namespace Artax;

use ArrayIterator,
    IteratorAggregate,
    Spl\TypeException,
    Spl\EmptyException,
    Artax\Http\Response;

class ClientResult implements IteratorAggregate, Response {
    
    private $response;
    private $requestUri;
    private $wasRedirected;
    private $responseChain;
    
    public function __construct(array $responses) {
        $this->response = current($responses);
        $this->requestUri = key($responses);
        $this->wasRedirected = count($responses) > 1;
        $this->responseChain = $responses;
    }
    
    public function getRequestUri() {
        return $this->requestUri;
    }
    
    public function wasRedirected() {
        return $this->wasRedirected;
    }
    
    public function getIterator() {
        return new ArrayIterator($this->responseChain);
    }
    
    public function getStatusCode() {
        return $this->response->getStatusCode();
    }
    
    public function getReasonPhrase() {
        return $this->response->getReasonPhrase();
    }
    
    public function getStartLine() {
        return $this->response->getStartLine();
    }
    
    public function getProtocol() {
        return $this->response->getProtocol();
    }
    
    public function getProtocolMajor() {
        return $this->response->getProtocolMajor();
    }
    
    public function getProtocolMinor() {
        return $this->response->getProtocolMinor();
    }
    
    public function hasHeader($field) {
        return $this->response->hasHeader($field);
    }
    
    public function getHeaders($field) {
        return $this->response->getHeaders($field);
    }

    public function getAllHeaders() {
        return $this->response->getAllHeaders();
    }
    
    public function getCombinedHeader($field) {
        return $this->response->getCombinedHeader($field);
    }
    
    public function getBody() {
        return $this->response->getBody();
    }
    
    public function __toString() {
        return $this->response->__toString();
    }
}