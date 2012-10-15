<?php

namespace Artax;

use LogicException,
    Artax\Http\StdResponse;

/**
 * Attaches request URIs to Response instances allowing determination of the final URI endpoint
 * when Location redirects occur. The class also allows traversal of each Response in a chain
 * so that the full redirect history can be examined.
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
     * @throws \Spl\ValueException On invalid URI
     * @return void
     */
    public function __construct($requestUri) {
        $this->requestUri = new Uri($requestUri);
    }
    
    /**
     * Retrieve the request URI that resulted in current response
     * 
     * @return string
     * @throws NotRedirectedException
     */
    public function getRequestUri() {
        return $this->requestUri->__toString();
    }
    
    /**
     * Assign the response from which the current response was redirected
     * 
     * @param ChainableResponse $previousResponse
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