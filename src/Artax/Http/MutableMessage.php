<?php

namespace Artax\Http;

interface MutableMessage {
    
    /**
     * @param string $version
     */
    function setHttpVersion($version);

    /**
     * @param string $headerName
     * @param string $value
     */
    function setHeader($headerName, $value);
    
    /**
     * @param string $rawHeaderString
     */
    function setRawHeader($rawHeaderString);
    
    /**
     * @param mixed $traversableHeaders
     */
    function setAllHeaders($traversableHeaders);
    
    /**
     * @param string $headerName
     */
    function removeHeader($headerName);
    
    /**
     * @param string $body
     */
    function setBody($body);
    
    function removeBody();
    
    /**
     * @param string $rawMessageStr
     */
    function populateFromRawMessage($rawMessageStr);
    
    function clearAll();
    
    function validateMessage();
    
}
