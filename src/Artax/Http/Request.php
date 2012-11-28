<?php

namespace Artax\Http;

/**
 * Adds Request-specific accessor methods to the base Message interface
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
 * @codeCoverageIgnore
 */
interface Request extends Message {
    
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const HEAD = 'HEAD';
    const DELETE = 'DELETE';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const CONNECT = 'CONNECT';
    
    /**
     * Access the HTTP method verb
     */
    function getMethod();

    /**
     * Access the request URI string
     */
    function getUri();
}