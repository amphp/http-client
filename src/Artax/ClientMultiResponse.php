<?php

namespace Artax;

use Iterator,
    Countable,
    Exception,
    Spl\TypeException,
    Artax\Http\Response;

class ClientMultiResponse implements Iterator, Countable {
    
    /**
     * @var array
     */
    private $responses;
    
    /**
     * @param array $responsesAndErrors
     * @return void
     * @throws \Spl\TypeException
     */
    public function __construct(array $responsesAndErrors) {
        if (!$this->validateResponses($responsesAndErrors)) {
            throw new TypeException(
                get_class($this) . "::__construct expects an array of Responses and/or " .
                'exceptions at Argument 1'
            );
        }
        $this->responses = $responsesAndErrors;
    }
    
    /**
     * @param array $responses
     * @return bool
     */
    private function validateResponses(array $responses) {
        if (!$responses) {
            return false;
        }
        
        foreach ($responses as $response) {
            if ($response instanceof Response || $response instanceof Exception) {
                continue;
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Were exceptions encountered while receiving any of the requests?
     * 
     * Note that this DOES NOT include 4xx and 5xx responses, which are not the same as an
     * actual exception encountered while processing a request.
     * 
     * @return bool
     */
    public function hasErrors() {
        return (bool) $this->getAllErrors();
    }
    
    /**
     * Get an array of all exception objects thrown and caught while processing the requests.
     * 
     * The returned array keys match the keys used in the request array/traversable passed to the
     * Client::sendMulti method.
     * 
     * @return array[Exception]
     */
    public function getAllErrors() {
        return array_filter($this->responses, function($x){ return $x instanceof Exception; });
    }
    
     /**
     * Get an array of all responses received from the multi-request.
     * 
     * The returned array keys match the keys used in the request array/traversable passed to the
     * Client::sendMulti method.
     * 
     * @return array[Response]
     */
    public function getAllResponses() {
        return array_filter($this->responses, function($x){ return $x instanceof Response; });
    }
    
    /**
     * Is the request result at the current iterator position an exception object?
     * 
     * @return bool
     */
    public function isError() {
        $current = $this->current();
        return $current instanceof Exception;
    }
    
    /**
     * Is the request result at the current iterator position a response object?
     * 
     * @return bool
     */
    public function isResponse() {
        $current = $this->current();
        return $current instanceof Response;
    }
    
    /**
     * Returns a combined count of responses and errors encountered during the multi-request.
     * 
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
