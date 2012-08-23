<?php

namespace Artax\Http;

interface RedirectionResponse extends Response {
    
    /**
     * Did this response result from the redirection of another response?
     */
    function resultedFromRedirection();
    
    /**
     * Retrieve the final, redirected URI of the resource modeled by this response
     */
    function getRedirectionUri();
    
    /**
     * Get the response whose redirection resulted in the current response
     */
    function getPreviousResponse();
}
