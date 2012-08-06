<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException;

abstract class StdMessage implements Message {

    /**
     * @var array
     */
    protected $headers = array();
    
    /**
     * @var string
     */
    protected $body;

    /**
     * @var string
     */
    protected $httpVersion = '1.1';

    /**
     * @param string $headerName
     * @return bool
     */
    public function hasHeader($headerName) {
        // Headers are case-insensitive:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        return array_key_exists($capsHeader, $this->headers);
    }

    /**
     * @param string $headerName
     * @return string
     * @throws RuntimeException
     * @todo Figure out the best exception to throw. Perhaps one isn't needed.
     */
    public function getHeader($headerName) {
        if (!$this->hasHeader($headerName)) {
            throw new RuntimeException();
        }
        
        // Headers are case-insensitive:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        return $this->headers[$capsHeader];
    }

    /**
     * @return array
     */
    public function getAllHeaders() {
        return $this->headers;
    }
    
    /**
     * Access the entity body
     * 
     * If a resource stream is assigned to the body property, its entire contents will be read into
     * memory and returned as a string. To access the stream resource directly, use
     * StdMessage::getBodyStream().
     * 
     * @return string
     */
    public function getBody() {
        if (is_resource($this->body)) {
            rewind($this->body);
            $contents = stream_get_contents($this->body);
            rewind($this->body);
            return $contents;
        } else {
            return $this->body;
        }
    }
    
    /**
     * Access the entity body's resource stream directly
     * 
     * @return resource
     */
    public function getBodyStream() {
        return is_resource($this->body) ? $this->body : null;
    }
    
    /**
     * @return string The HTTP version number (not prefixed by `HTTP/`)
     */
    public function getHttpVersion() {
        return $this->httpVersion;
    }
    
    /**
     * @param string $headerName
     * @param string $value
     * @return void
     */
    protected function assignHeader($headerName, $value) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $normalizedHeader = rtrim(strtoupper($headerName), ': ');
        $this->headers[$normalizedHeader] = $value;
    }
    
    /**
     * @param mixed $iterable
     * @return void
     * @throws InvalidArgumentException
     */
    protected function assignAllHeaders($iterable) {
        if (!($iterable instanceof Traversable
            || $iterable instanceof StdClass
            || is_array($iterable)
        )) {
            $type = is_object($iterable) ? get_class($iterable) : gettype($iterable);
            throw new InvalidArgumentException(
                'Only an array, StdClass or Traversable object may be used to assign headers: ' .
                "$type specified"
            );
        }
        foreach ($iterable as $headerName => $value) {
            $this->assignHeader($headerName, $value);
        }
    }
    
    /**
     * @param array $headers
     * @return array
     */
    protected function normalizeHeaders($headers) {
        $normalized = array();
        
        foreach ($headers as $header => $value) {
            // Headers are case-insensitive as per the HTTP spec:
            // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
            $fixedHeader = rtrim(strtoupper($header), ': ');
            $normalized[$fixedHeader] = $value;
        }
        
        return $normalized;
    }
}
