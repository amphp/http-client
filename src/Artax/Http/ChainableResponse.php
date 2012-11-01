<?php

namespace Artax\Http;

use LogicException,
    Artax\Uri;

/**
 * Extends StdResponse to allow traversal of redirected responses and access to the redirect URI
 */
class ChainableResponse extends StdResponse {
    
    /**
     * @var Uri
     */
    private $requestUri;
    
    /**
     * @var ChainableResponse
     */
    private $previousResponse;
    
    /**
     * @param string $requestUri The request URI that led to this response
     */
    public function __construct($requestUri) {
        $this->requestUri = new Uri($requestUri);
    }
    
    /**
     * Retrieve the request URI that resulted in the current response
     * 
     * @return string
     */
    public function getRequestUri() {
        return $this->requestUri->__toString();
    }
    
    /**
     * Store the response whose redirection resulted in the current response instance
     * 
     * @param ChainableResponse $previousResponse The previous response in the redirect chain
     * @return void
     */
    public function setPreviousResponse(ChainableResponse $previousResponse) {
        $this->previousResponse = $previousResponse;
    }
    
    /**
     * Did this response result from the redirection of another response?
     * 
     * @return bool
     */
    public function hasPreviousResponse() {
        return !empty($this->previousResponse);
    }
    
    /**
     * Get the response whose redirection resulted in the current response
     * 
     * @return ChainableResponse
     * @throws \LogicException
     */
    public function getPreviousResponse() {
        if (!$this->hasPreviousResponse()) {
            throw new LogicException(
                'Response did not result from redirection'
            );
        }
        
        return $this->previousResponse;
    }
}