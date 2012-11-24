<?php

namespace Artax;

use Iterator,
    Countable,
    Spl\TypeException,
    Spl\EmptyException,
    Spl\LookupException,
    Spl\FunctionException;

class ClientResponse implements Iterator, Countable {
    
    private $responseChain;
    
    public function __construct(array $responseList) {
        $this->validateResponseList($responseList);
        $this->responseChain = $responseList;
    }
    
    private function validateResponseList($responseList) {
        if (!count($responseList)) {
            throw new EmptyException(
                get_class($this) . ' expects a non-empty Response list'
            );
        }
        foreach ($responseList as $response) {
            if (!$response instanceof Http\Response) {
                throw new TypeException(
                    get_class($this) . ' expects a traversable list of Response instances'
                );
            }
        }
    }
    
    public function __call($method, $args) {
        if (!$this->valid()) {
            throw new LookupException(
                'No response at the current iterator position'
            );
        }
        
        $currentResponse = $this->current();
        $callable = array($currentResponse, $method);
        
        if (is_callable($callable)) {
            return call_user_func_array(array($currentResponse, $method), $args);
        } else {
            throw new FunctionException(
                "Invalid method: " . get_class($this) . "::$method does not exist"
            );
            
        }
    }
    
    public function __toString() {
        if ($this->valid()) {
            $currentResponse = $this->current();
            return $currentResponse->__toString();
        } else {
            return '';
        }
    }
    
    public function getRequestUri() {
        return $this->key();
    }
    
    public function count() {
        return count($this->responseChain);
    }
    
    public function rewind() {
        return reset($this->responseChain);
    }
    
    public function current() {
        return current($this->responseChain);
    }
    
    public function key() {
        return key($this->responseChain);
    }
    
    public function next() {
        return next($this->responseChain);
    }
    
    public function valid() {
        return key($this->responseChain) !== null;
    }
}