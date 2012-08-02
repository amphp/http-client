<?php

namespace Artax\Http;

interface FormEncodableRequest extends Request {
    
    /**
     * @param string $parameterName
     */
    public function hasBodyParameter($parameterName);
    
    /**
     * @param string $parameterName
     */
    public function getBodyParameter($parameterName);
    
    public function getAllBodyParameters();
}
