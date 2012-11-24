<?php

namespace Artax\Http;

/**
 * Message is the base interface for Request and Response instances.
 * 
 * Message specifically leaves setter methods undefined to act as a basis for immutable value object
 * modelings of HTTP messages. It is the base interface for the Request and Response interfaces.
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
 */
interface Message {
    
    const HTTP_PROTOCOL_PREFIX = 'HTTP/';
    
    /**
     * Get the message start line
     * 
     * @return string
     */
    function getStartLine();
    
    /**
     * Retrieve the message's numerical HTTP protocol version number (without the "HTTP/" prefix)
     * 
     * @return string
     */
    function getProtocol();
    function getProtocolMajor();
    function getProtocolMinor();
    
    /**
     * Does the message contain the specified header field?
     * 
     * @param string $field
     * @return bool
     */
    function hasHeader($field);
    
    /**
     * Retrieve an iterator containing all headers for the given field
     * 
     * Implementations should return headers in a case-insensitive manner as header fields
     * are NOT case-sensitive (per RFC 2616 Section 4.2). For example, the following lines should
     * each return equivalent result:
     * 
     * ```php
     * <?php
     * $cookies = $msgImpl->getHeaders('Cookie');
     * $cookies = $msgImpl->getHeaders('COOKIE');
     * $cookies = $msgImpl->getHeaders('CoOkIe');
     * ```
     * 
     * @param string $field
     * @return \Iterator
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
     */
    function getHeaders($field);

    /**
     * Retrieve an iterator containing all assigned message headers
     * 
     * @return \Iterator
     */
    function getAllHeaders();
    
    /**
     * Directly retrieve a header value (or a set of comma-concatenated values if multiples exist)
     * 
     * @param string $field
     * @return string
     */
    function getCombinedHeader($field);
    
    /**
     * Retrieve the message entity body
     * 
     * @return string
     */
    function getBody();
    
    /**
     * Retrieve a raw HTTP message given the current object properties
     * 
     * @return string
     */
    function __toString();
}
