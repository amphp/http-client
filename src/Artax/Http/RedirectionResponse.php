<?php

namespace Artax\Http;

use Artax\Http\Exceptions\NotRedirectedException;

class RedirectionResponse extends StdResponse {
    
    /**
     * @var RedirectionResponse
     */
    protected $redirectedFrom;
    
    /**
     * Assign the response object from which this response was redirected
     * 
     * @param RedirectionResponse $redirectedFrom
     * @return void
     */
    public function setPreviousResponse(RedirectionResponse $redirectedFrom) {
        $this->redirectedFrom = $redirectedFrom;
    }
    
    /**
     * Did this response result from the redirection of another response?
     * 
     * @return bool
     */
    public function resultedFromRedirection() {
        return !empty($this->redirectedFrom);
    }
    
    /**
     * Retrieve the final, redirected URI of the resource returned by this response
     * 
     * @return string
     * @throws NotRedirectedException
     */
    public function getRedirectionUri() {
        if (!$this->resultedFromRedirection()) {
            throw new NotRedirectedException(
                'Response did not result from redirection'
            );
        }
        
        return $this->redirectedFrom->getHeader('Location');
    }
    
    /**
     * Get the response whose redirection resulted in the current response
     * 
     * @return RedirectionResponse
     * @throws NotRedirectedException
     */
    public function getPreviousResponse() {
        if (!$this->resultedFromRedirection()) {
            throw new NotRedirectedException(
                'Response did not result from redirection'
            );
        }
        
        return $this->redirectedFrom;
    }
}