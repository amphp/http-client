<?php

namespace Artax\Http;

use Iterator,
    Countable,
    OutOfBoundsException;

class ClientMultiResponse implements Iterator, Countable {
    
    /**
     * @var array
     */
    private $requestKeys;
    
    /**
     * @var array
     */
    private $responses;
    
    /**
     * @var array
     */
    private $exceptions;
    
    /**
     * @param array $responses
     */
    public function __construct(array $responses) {
        $this->requestKeys = array_keys($responses);
        $this->responses = array_filter($responses, function($r) { return $r instanceof Response; });
        $this->exceptions = array_diff_key($responses, $this->responses);
    }
    
    /**
     * @return bool
     */
    public function errorsOccurred() {
        return !empty($this->exceptions);
    }
    
    /**
     * @return array
     */
    public function getAllErrors() {
        return $this->exceptions;
    }
    
    /**
     * @return array
     */
    public function getAllResponses() {
        return $this->responses;
    }
    
    /**
     * @param string $requestKey
     * @return bool
     */
    public function hasResponse($requestKey) {
        return isset($this->responses[$requestKey]);
    }
    
    /**
     * @param string $requestKey
     * @return ClientResponse
     * @throws OutOfBoundsException
     */
    public function getResponse($requestKey) {
        if ($this->hasResponse($requestKey)) {
            return $this->responses[$requestKey];
        }
        
        $msg = in_array($requestKey, $this->requestKeys)
            ? "The request at key $requestKey resulted in an exception and no response is available"
            : "No request was made at key $requestKey";
            
        throw OutOfBoundsException($msg);
    }
    
    /**
     * @return bool
     */
    public function hasError($requestKey) {
        return isset($this->exceptions[$requestKey]);
    }
    
    /**
     * @param string $requestKey
     * @return Exception
     * @throws OutOfBoundsException
     */
    public function getError($requestKey) {
        if ($this->hasError($requestKey)) {
            return $this->exceptions[$requestKey];
        }
        
        $msg = in_array($requestKey, $this->requestKeys)
            ? "The request at key $requestKey did not result in an exception"
            : "No request was made at key $requestKey";
            
        throw OutOfBoundsException($msg);
    }
    
    /**
     * @return int
     */
    public function count() {
        return count($this->responses);
    }
    
    public function rewind() {
        return reset($this->responses);
    }
    
    public function current() {
        return current($this->responses);
    }
    
    public function key() {
        return key($this->responses);
    }
    
    public function next() {
        return next($this->responses);
    }
    
    public function valid() {
        return key($this->responses) !== null;
    }
}
