<?php

namespace Artax\Http;

interface FormEncodableRequest extends Request {
    
    /**
     * Does the request contain the specified body parameter?
     * 
     * @param string $parameterName
     */
    public function hasBodyParameter($parameterName);
    
    /**
     * Retrieve the value of a specific body parameter
     * 
     * @param string $parameterName
     */
    public function getBodyParameter($parameterName);
    
    /**
     * Retrieve a key-value list of body parameters
     */
    public function getAllBodyParameters();
}
