<?php

namespace Artax;

/**
 * An interface to allow custom and mixed HTTP message bodies
 */
interface BodyAggregate {
    
    /**
     * Returns the raw HTTP message body
     * 
     * @return mixed Must return a scalar value or Iterator instance
     */
    function getBody();
    
    /**
     * Defines the HTTP Content-Type for this message body
     * 
     * @return string The Content-Type associated with the content returned by BodyAggregate::getBody()
     */
    function getContentType();
    
}
