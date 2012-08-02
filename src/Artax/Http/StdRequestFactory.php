<?php

namespace Artax\Http;

use DomainException;

class StdRequestFactory {
    
    /**
     * @var RequestDetector
     */
    private $requestDetector;
    
    /**
     * @param StdRequestDetector $requestDetector
     */
    public function __construct(StdRequestDetector $detector) {
        $this->requestDetector = $detector;
    }
    
    /**
     * @return Request
     * @throws DomainException
     */
    public function make(array $_server) {
        $uri = $this->requestDetector->detectUri($_server);
        $httpVersion = $this->requestDetector->detectHttpVersion($_server);
        $method = $this->requestDetector->detectMethod($_server);
        $headers = $this->requestDetector->detectHeaders($_server);
        $body = $this->requestDetector->detectBody();
        
        return new StdRequest($uri, $method, $headers, $body, $httpVersion);
    }
}
