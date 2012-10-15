<?php

namespace Artax\Http;

/**
 * This interface is modeled after RFC 2616, section 4:
 * 
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
 * 
 * Message is the base interface for Request and Response instances.
 */
interface Message {

    /**
     * Retrieve the HTTP message entity body in string form
     * 
     * @return string
     */
    function getBody();
    
    /**
     * Retrieve the HTTP message entity body as a stream resource (if available)
     * 
     * @return resource Returns body stream or NULL if the message body IS NOT a stream resource
     */
    function getBodyStream();
    
    /**
     * Assign an entity body to the HTTP message
     * 
     * @param mixed $bodyStringOrResource A string or stream resource
     * @return void
     */
    function setBody($bodyStringOrResource);
    
    /**
     * Does the HTTP message contain the specified header field?
     * 
     * @param string $headerName
     * @return bool
     */
    function hasHeader($headerName);
    
    /**
     * Retrieve the value of the specified header field
     * 
     * @param string $headerName
     * @return string
     */
    function getHeader($headerName);

    /**
     * Retrieve a traversable name-value array of header fields and their values
     * 
     * @return array
     */
    function getAllHeaders();
    
    /**
     * Retrieve the message headers as they would appear in an HTTP message
     * 
     * @return string
     */
    function getRawHeaders();

    /**
     * Assign a message header
     * 
     * @param string $headerName
     * @param string $value
     * @return void
     */
    function setHeader($headerName, $value);
    
    /**
     * Assign all header values from a traversable key-value list of header fields and their values
     * 
     * @param mixed $traversableHeaders
     * @return void
     */
    function setAllHeaders($traversableHeaders);
    
    /**
     * Assign a header value from a raw string (e.g. Header-Name: some value)
     * 
     * @param string $rawHeaderStr
     * @return void
     */
    function setRawHeader($rawHeaderStr);
    
    /**
     * Clear all existing headers and assign matched headers from the specified raw string
     * 
     * @param string $rawHeaderStr
     * @return void
     */
    function setAllRawHeaders($rawHeaderStr);
    
    /**
     * Append a header to the existing collection
     *
     * @param string $headerName
     * @param mixed $value A string or single-dimensional array of strings
     * @return void
     */
    function appendHeader($headerName, $value);
    
    /**
     * Assign or append headers from a traversable without clearing previously assigned values
     *
     * @param mixed $iterable
     * @return void
     */
    function appendAllHeaders($iterable);
    
    /**
     * Remove the specified header from the message
     * 
     * @param string $headerName
     * @return void
     */
    function removeHeader($headerName);
    
    /**
     * Remove all assigned headers from the message
     * 
     * @return void
     */
    function removeAllHeaders();
    
    /**
     * Retrieve the numerical HTTP version supported by the message (without the "HTTP/" prefix)
     * 
     * @return int
     */
    function getHttpVersion();
    
    /**
     * Assign the HTTP version supported by the message (without the "HTTP/" prefix)
     * 
     * @param float $decimalVersionNumer
     * @return void
     */
    function setHttpVersion($decimalVersionNumer);

    /**
     * Retrieve the HTTP message formatted for transport
     * 
     * @return string
     */
    function __toString();
}
