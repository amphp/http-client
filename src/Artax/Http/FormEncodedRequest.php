<?php

namespace Artax\Http;

use RuntimeException;

class FormEncodedRequest extends StdRequest implements FormEncodableRequest {
    
    /**
     * @var array
     */
    protected $bodyParameters = array();
    
    /**
     * @var bool
     */
    protected $bodyParamsAreParsed = false;
    
    /**
     * Does the specified body parameter exist?
     * 
     * @param string $parameter
     * @return bool
     */
    public function hasBodyParameter($parameterName) {
        if (!$this->bodyParamsAreParsed) {
            $this->parseBodyParameters();
        }
        return isset($this->bodyParameters[$parameterName]);
    }
    
    /**
     * Access the specified body parameter value
     * 
     * @param string $parameterName
     * @return string
     * @todo Determine appropriate exception for invalid parameter request
     */
    public function getBodyParameter($parameterName) {
        if (!$this->bodyParamsAreParsed) {
            $this->parseBodyParameters();
        }
        if (!$this->hasBodyParameter($parameterName)) {
            throw new RuntimeException;
        }
        return $this->bodyParameters[$parameterName];
    }
    
    /**
     * Access an array of all body parameters
     * 
     * @return array
     */
    public function getAllBodyParameters() {
        if (!$this->bodyParamsAreParsed) {
            $this->parseBodyParameters();
        }
        return $this->bodyParameters;
    }
    
    /**
     * Parses a form-encoded request body into a key-value array of parameters
     * 
     * @return void
     */
    protected function parseBodyParameters() {
        if ($this->body && $this->hasFormEncodedBody()) {
            $this->bodyParameters = $this->parseParametersFromString($this->getBody());
        } else {
            $this->bodyParameters = array();
        }
        $this->bodyParamsAreParsed = true;
    }
    
    /**
     * Does the request have an encoded body?
     * 
     * @return bool
     */
    protected function hasFormEncodedBody() {
        if (!$this->hasHeader('Content-Type')) {
            return false;
        }
        
        return !strcmp($this->getHeader('Content-Type'), 'application/x-www-form-urlencoded');
    }
}
