<?php

namespace Artax\Http;

use Iterator,
    Countable,
    Spl\TypeException,
    Spl\ValueException,
    Spl\DomainException;

class HeaderCollection implements Iterator, Countable {
    
    /**
     * @var array
     */
    private $headers = array();
    
    /**
     * @return string
     */
    public function __toString() {
        $str = '';
        foreach ($this->headers as $headerObj) {
            $str .= $headerObj->__toString();
        }
        return $str;
    }
    
    /**
     * Does the specified header exist in the collection?
     * 
     * @param string $headerName
     * @return bool
     */
    public function hasHeader($headerName) {
        $normalizedHeaderName = $this->normalizeName($headerName);
        return isset($this->headers[$normalizedHeaderName]);
    }
    
    /**
     * @param string $headerName
     * @return string
     */
    private function normalizeName($headerName) {
        return strtoupper(rtrim($headerName, ': '));
    }
    
    /**
     * Does the specified header have multiple values assigned to it?
     * 
     * @param string $headerName
     * @return bool
     * @throws Spl\DomainException
     */
    public function isMultiHeader($headerName) {
        if ($this->hasHeader($headerName)) {
            $normalizedHeaderName = $this->normalizeName($headerName);
            return count($this->headers[$normalizedHeaderName]) > 1;
        }
        
        throw new DomainException(
            "The specified header $headerName does not exist"
        );
    }
    
    /**
     * Retrieve the string value of the specified header
     * 
     * If multiple values are assigned to the specified header, they will be concatenated together
     * and delimited by commas.
     * 
     * @param string $headerName
     * @return string
     * @throws Spl\DomainException
     */
    public function getHeader($headerName) {
        if ($this->hasHeader($headerName)) {
            $normalizedHeaderName = $this->normalizeName($headerName);
            $header = $this->headers[$normalizedHeaderName];
            return $header->getValue();
        }
        
        throw new DomainException(
            "The specified header $headerName does not exist"
        );
    }
    
    /**
     * Retrieve an array of values assigned for the specified header
     * 
     * @param string $headerName
     * @return array
     * @throws Spl\DomainException
     */
    public function getHeaderValueArray($headerName) {
        $header = $this->getHeader($headerName);
        return $header->getValueArray();
    }
    
    /**
     * @param string $headerName
     * @param mixed $value A string or single-dimensional array of strings
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function setHeader($headerName, $value) {
        $header = $this->makeHeader($headerName, $value);
        $normalizedHeaderName = $this->normalizeName($headerName);
        $this->headers[$normalizedHeaderName] = $header;
    }
    
    /**
     * @param string $headerName
     * @param mixed $value
     * @return Artax\Http\Header
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    protected function makeHeader($headerName, $value) {
        return new Header($headerName, $value);
    }
    
    /**
     * Add a header to the collection
     * 
     * @param string $headerName
     * @param mixed $value A string or single-dimensional array of strings
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function appendHeader($headerName, $value) {
        $normalizedHeaderName = $this->normalizeName($headerName);
        if (isset($this->headers[$normalizedHeaderName])) {
            $header = $this->headers[$normalizedHeaderName];
            $header->appendValue($value);
        } else {
            $this->setHeader($headerName, $value);
        }
    }
    
    /**
     * Remove a specific header from the collection
     * 
     * @param string $headerName
     * @return void
     */
    public function removeHeader($headerName) {
        $normalizedHeaderName = $this->normalizeName($headerName);
        unset($this->headers[$normalizedHeaderName]);
    }
    
    /**
     * Clear all assigned HTTP headers from the collection
     * 
     * @return void
     */
    public function removeAllHeaders() {
        $this->headers = array();
    }
    
    /**
     * Output all HTTP headers in the collection
     * 
     * @return void
     */
    public function send() {
        foreach ($this->headers as $header) {
            $header->send();
        }
    }
    
    public function count() {
        return count($this->headers);
    }
    
    public function rewind() {
        return reset($this->headers);
    }
    
    public function current() {
        return current($this->headers);
    }
    
    public function key() {
        return key($this->headers);
    }
    
    public function next() {
        return next($this->headers);
    }
    
    public function valid() {
        return key($this->headers) !== null;
    }
}
