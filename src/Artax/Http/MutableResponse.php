<?php

namespace Artax\Http;

/**
 * Adds setters for mutability of Response-specific message properties
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html
 */
interface MutableResponse extends MutableMessage, Response {
    
    /**
     * Assign the numeric response status code
     * 
     * @param int $statusCode
     */
    function setStatusCode($statusCode);
    
    /**
     * Assign the start line reason phrase
     * 
     * @param string $reasonPhrase
     */
    function setReasonPhrase($reasonPhrase);
}
