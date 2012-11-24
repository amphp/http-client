<?php

namespace Artax\Http;

/**
 * MutableMessage is the base interface for the mutable StdRequest and StdResponse classes. It adds
 * modifier methods to the base Message interface to allow incremental creation/modification of
 * message implementations. 
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
 */
interface MutableMessage extends Message {
    
    /**
     * Replace all values in the current instance with those of another Message instance
     * 
     * @param $requestOrResponse An external message instance from which to import values
     */
    function import($requestOrResponse);
    
    /**
     * Export a new immutable message from the current properties
     */
    function export();
    
    /**
     * Assign the HTTP version supported by the message (without the "HTTP/" prefix)
     * 
     * @param string $decimalVersionNumer
     * @return void
     */
    function setProtocol($decimalVersionNumer);
    
    /**
     * Assign an entity body to the HTTP message
     * 
     * @param mixed $bodyStringOrResource A string or stream resource
     * @return void
     */
    function setBody($bodyStringOrResource);
    
    /**
     * Assign a message header
     * 
     * @param string $field
     * @param string $value
     * @return void
     */
    function setHeader($field, $value);
    
    /**
     * Assign all header values from a traversable key-value list of header fields and their values
     * 
     * @param mixed $traversableHeaders
     * @return void
     */
    function setAllHeaders($traversableHeaders);
    
    /**
     * Append a header to the existing collection
     *
     * @param string $field
     * @param mixed $value A string or single-dimensional array of strings
     * @return void
     */
    function addHeader($field, $value);
    
    /**
     * Assign or append headers from a traversable without clearing previously assigned values
     *
     * @param mixed $iterable
     * @return void
     */
    function addAllHeaders($iterable);
    
    /**
     * Remove the specified header from the message
     * 
     * @param string $field
     * @return void
     */
    function removeHeader($field);
    
    /**
     * Remove all assigned headers from the message
     * 
     * @return void
     */
    function removeAllHeaders();
}
