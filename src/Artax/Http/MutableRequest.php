<?php

namespace Artax\Http;

/**
 * Adds setters for mutability of Request-specific message properties
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
 */
interface MutableRequest extends MutableMessage, Request {
    
    /**
     * Assign the HTTP method verb
     * 
     * @param int $methodVerb
     */
    function setMethod($methodVerb);
    
    /**
     * Set the request URI
     * 
     * @param string $requestUri
     */
    function setUri($requestUri);
}
