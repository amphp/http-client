<?php

namespace Artax;

use Iterator,
    Countable,
    ArrayIterator,
    Ardent\KeyException;

class ClientMultiResult implements Iterator, Countable {
    
    /**
     * @var array
     */
    private $responses;
    
    /**
     * @var array
     */
    private $errors;

    /**
     * @param array $responses
     * @param array $errors
     */
    public function __construct(array $responses, array $errors) {
        $this->responses = $responses;
        $this->errors = $errors;
    }
    
    /**
     * Retrieve the result (completed Response or Exception object) for the specified request key
     * 
     * @param string $requestKey The key used to identify the request in the sendMulti batch.
     * @throws \Ardent\KeyException If the specified request key does not exist
     * @return mixed An Artax\ClientResult or Exception object if an error halted retrieval
     */
    public function getResult($requestKey) {
        if (isset($this->responses[$requestKey])) {
            return $this->responses[$requestKey];
        } elseif (isset($this->errors[$requestKey])) {
            return $this->errors[$requestKey];
        } else {
            throw new KeyException(
                'Invalid request key'
            );
        }
    }
    
    /**
     * Get an array of all responses received from the multi-request.
     * 
     * Returned array keys match the keys used in the request list passed to `Client::sendMulti()`.
     * Request keys that encountered an error during retrieval have a null value.
     * 
     * @return \ArrayIterator
     */
    public function getAllResponses() {
        return new ArrayIterator($this->responses);
    }
    
    /**
     * Were exceptions encountered while receiving any of the requests?
     * 
     * Note that this DOES NOT include 4xx and 5xx responses (which are NOT the same as an
     * actual exception encountered while processing a request).
     * 
     * @return bool
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Get an ArrayIterator holding all exceptions thrown while processing the request batch.
     * 
     * Iterator keys match those used in the request list passed to `Client::sendMulti()`.
     * 
     * @return \ArrayIterator
     */
    public function getAllErrors() {
        return new ArrayIterator($this->errors);
    }
    
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
