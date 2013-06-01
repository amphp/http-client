<?php

namespace Artax;

class Request extends Message {
    
    private $method;
    private $uri;
    
    function getMethod() {
        return $this->method;
    }
    
    function setMethod($method) {
        $this->method = (string) $method;
        
        return $this;
    }
    
    function getUri() {
        return $this->uri;
    }
    
    function setUri($uri) {
        $this->uri = (string) $uri;
        
        return $this;
    }
    
}

