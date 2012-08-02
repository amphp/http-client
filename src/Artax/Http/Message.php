<?php

namespace Artax\Http;

/**
 * This interface is modeled after RFC 2616, section 4:
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
 */
interface Message {
    
    /**
     * @return string Returns the HTTP version number, not prefixed by `HTTP/`
     */
    function getHttpVersion();

    /**
     * @param string $headerName
     * @return string
     */
    function getHeader($headerName);

    /**
     * @return array
     */
    function getAllHeaders();

    /**
     * @param string $headerName
     * @return bool
     */
    function hasHeader($headerName);
    
    /**
     * @return string
     */
    function getBody();
    
    /**
     * @return string
     */
    function __toString();
}
