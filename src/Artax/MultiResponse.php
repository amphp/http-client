<?php

namespace Artax;

use Iterator,
    Countable,
	Exception,
    Spl\DomainException,
    Artax\Http\Response;

class MultiResponse implements Iterator, Countable {
	
	/**
	 * @var array
	 */
	private $responses;
    
    /**
     * @param array $responsesAndErrors
     */
    public function __construct(array $responsesAndErrors) {
		$this->responses = $responsesAndErrors;
    }
    
    /**
     * @return int
     */
    public function getErrorCount() {
        return count($this->getAllErrors());
    }
    
    /**
     * @return array
     */
    public function getAllErrors() {
        return array_filter($this->responses, function($x){ return $x instanceof Exception; });
    }
    
    /**
     * @param string $requestKey
     * @return Exception
     * @throws Spl\DomainException
     */
    public function getError($requestKey) {
		if (!isset($this->responses[$requestKey])) {
			throw new DomainException(
				"No request assigned to key: $requestKey"
			);
		}
        if (!$this->responses[$requestKey] instanceof Exception) {
            throw new DomainException(
				"The request at key $requestKey did not encounter any errors"
			);
        }
        
		return $this->responses[$requestKey];
    }
    
    /**
     * @return array
     */
    public function getAllResponses() {
        return array_filter($this->responses, function($x){ return $x instanceof Response; });
    }
    
    /**
     * @param string $requestKey
     * @return Artax\Http\Response
     * @throws Spl\DomainException
     */
    public function getResponse($requestKey) {
        if (!isset($this->responses[$requestKey])) {
			throw new DomainException(
				"No request assigned to key: $requestKey"
			);
		}
        if (!$this->responses[$requestKey] instanceof Response) {
            throw new DomainException(
				"The request at key $requestKey encountered an error and no response is available"
			);
        }
        
		return $this->responses[$requestKey];
    }
    
	/**
	 * Is the request result at the current iterator entry an exception object?
	 * 
	 * @return bool
	 */
	public function isError() {
		$current = $this->current();
		return $current instanceof Exception;
	}
	
	/**
	 * Is the request result at the current iterator entry a response object?
	 * 
	 * @return bool
	 */
	public function isResponse() {
		$current = $this->current();
		return $current instanceof Response;
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
