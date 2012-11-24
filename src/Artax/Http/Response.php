<?php

namespace Artax\Http;

/**
 * Adds Response-specific accessor methods to the base Message interface
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html
 * @codeCoverageIgnore
 */
interface Response extends Message {
    
    /**
     * Get the status code (100-599) assigned to the response
     */
    function getStatusCode();
    
    /**
     * Get the reason phrase that accompanies the response's status code in the start line
     */
    function getReasonPhrase();
}
