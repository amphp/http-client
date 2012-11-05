<?php

namespace Artax\Http;

/**
 * Interface modeled after RFC 2616 Section 6
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html
 */
interface Response extends Message {
    
    /**
     * Get the status code (100-599) assigned to the response
     */
    function getStatusCode();
    
    /**
     * Assign the numeric response status code
     */
    function setStatusCode($statusCode);
    
    /**
     * Get the reason phrase that accompanies the response's status code in the start line
     */
    function getReasonPhrase();
    
    /**
     * Assign the start line reason phrase
     * 
     * @param string $reasonPhrase
     */
    function setReasonPhrase($reasonPhrase);
}
