<?php

namespace Artax\Http;

use StdClass,
    Traversable,
    ArrayIterator,
    Spl\KeyException,
    Spl\TypeException,
    Spl\DomainException,
    Spl\LookupException;

abstract class ValueMessage implements Message {
    
    /**
     * @var int
     */
    private $protocolMajor;
    
    /**
     * @var int
     */
    private $protocolMinor;
    
    /**
     * @var array
     */
    private $headers = array();
    
    /**
     * @var string
     */
    private $body;
    
    /**
     * Retrieve the message's numerical HTTP protocol version
     *
     * @return string
     */
    public function getProtocol() {
        if (!is_null($this->protocolMajor)) {
            return $this->protocolMajor . '.' . $this->protocolMinor;
        } else {
            return null;
        }
    }
    
    /**
     * @return int
     */
    public function getProtocolMajor() {
        return $this->protocolMajor;
    }
    
    /**
     * @return int
     */
    public function getProtocolMinor() {
        return $this->protocolMinor;
    }

    /**
     * @param string $protocol
     * @throws \Spl\DomainException On invalid HTTP version (non-numeric or missing ".")
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.1
     */
    protected function assignProtocol($protocol) {
        $protocol = ($protocol === 1.0) ? '1.0' : $protocol;
        
        if (preg_match(",^\s*(?:HTTP/)?(\d+)\.(\d+)\s*$,i", $protocol, $matches)) {
            $this->protocolMajor = (int) ltrim($matches[1], '0');
            $this->protocolMinor = (int) $matches[2];
        } else {
            throw new DomainException(
                'Invalid HTTP version'
            );
        }
    }
    
    /**
     * @param mixed $scalarOrResource
     * @throws \Spl\TypeException If not a scalar or resource
     */
    protected function assignBody($scalarOrResource) {
        if (is_resource($scalarOrResource)) {
            $this->body = $scalarOrResource;
        } elseif (is_scalar($scalarOrResource) || is_null($scalarOrResource)) { 
            $this->body = (string) $scalarOrResource;
        } else {
            throw new TypeException(
                'Invalid entity body; scalar or stream resource required'
            );
        }
    }

    /**
     * Retrieve the HTTP message entity body
     * 
     * @return mixed String or stream resource
     */
    public function getBody() {
        return $this->body;
    }
    
    /**
     * Does the message contain the specified header?
     *
     * @param string $field
     * @return bool
     */
    public function hasHeader($field) {
        foreach ($this->headers as $header) {
            /**
             * @var Header $header
             */
            if (!strcasecmp($field, $header->getField())) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Retrieve a collection of all headers matching the specified field name
     * 
     * Headers are returned in a case-insensitive manner relative to the requested header field.
     * That is, the following lines will each return equivalent results:
     * 
     * ```php
     * <?php
     * $headerCollection = $msgImpl->getHeaders('Content-Length');
     * $headerCollection = $msgImpl->getHeaders('CONTENT-LENGTH');
     * $headerCollection = $msgImpl->getHeaders('CoNtEnT-LeNgTh');
     * ```
     * 
     * @param string $field The header field to retrieve
     * @throws \Spl\KeyException If no headers exist for the specified field
     * @return \ArrayIterator
     * 
     * @see ValueMessage::getAllHeaders
     * @see ValueMessage::getCombinedHeader
     */
    public function getHeaders($field) {
        $headers = array();
        foreach ($this->headers as $header) {
            /**
             * @var Header $header
             */
            if (!strcasecmp($field, $header->getField())) {
                $headers[] = $header;
            }
        }
        
        if (!empty($headers)) {
            return new ArrayIterator($headers);
        }
        
        throw new KeyException(
            'Specified header does not exist'
        );
    }
    
    /**
     * Directly retrieve a header value (or a set of comma-concatenated values if multiples exist)
     *  
     * Headers are returned in a case-insensitive manner relative to the requested header field.
     * That is, the following lines will each return equivalent results:
     * 
     * ```php
     * <?php
     * $contentLength = $msgImpl->getCombinedHeader('Content-Length');
     * $contentLength = $msgImpl->getCombinedHeader('CONTENT-LENGTH');
     * $contentLength = $msgImpl->getCombinedHeader('CoNtEnT-LeNgTh');
     * ```
     * 
     * @param string $field
     * @throws \Spl\KeyException If the specified header does not exist
     * @return string The combined value of all headers matching the specified field
     */
    public function getCombinedHeader($field) {
        $headers = $this->getHeaders($field);
        
        $result = '';
        foreach ($headers as $header) {
            /**
             * @var Header $header
             */
            $result .= $header->getValue() . ',';
        }
        
        return ($result !== '') ? substr($result, 0, -1) : '';
    }
    
    /**
     * Retrieve all message headers
     *
     * @return \ArrayIterator
     * @see ValueMessage::getHeaders
     */
    public function getAllHeaders() {
        return new ArrayIterator($this->headers);
    }

    /**
     * @throws \Spl\TypeException On non-string $field or $value parameter
     * @throws \Spl\DomainException On unacceptable header values (invalid characters)
     */
    protected function appendHeader($field, $value) {
        if (is_array($value)) {
            foreach ($value as $nVal) {
                $this->appendHeader($field, $nVal);
            }
        } else {
            $header = new Header($field, $value);
            $this->headers[] = $header;
        }
    }
    
    protected function appendAllHeaders($arrayOrTraversable) {
        if (!($arrayOrTraversable instanceof Traversable
            || $arrayOrTraversable instanceof StdClass
            || is_array($arrayOrTraversable)
        )) {
            throw new TypeException(
                "Invalid iterable; array, Traversable or StdClass expected"
            );
        }
        
        foreach ($arrayOrTraversable as $field => $value) {
            if ($value instanceof Header) {
                /**
                 * @var Header $value
                 */
                $this->appendHeader($value->getField(), $value->getValue());
            } else {
                $this->appendHeader($field, $value);
            }
        }
    }

    protected function clearHeader($field) {
        foreach ($this->headers as $key => $header) {
            /**
             * @var Header $header
             */
            if (!strcasecmp($field, $header->getField())) {
                unset($this->headers[$key]);
            }
        }
    }
    
    protected function clearAllHeaders() {
        $this->headers = array();
    }
    
    /**
     * Generate a raw HTTP message given the assigned properties
     * 
     * @return string
     */
    public function __toString() {
        $result = $this->getStartLine() . "\r\n";
        
        foreach ($this->headers as $header) {
            /**
             * @var Header $header
             */
            $result .= $header->getField() . ': ' . $header->getRawValue() . "\r\n";
        }
        
        $result .= "\r\n";
        
        $body = $this->getBody();
        
        if (is_resource($body)) {
            $currentBodyPos = ftell($body);
            $result .= stream_get_contents($body);
            fseek($body, $currentBodyPos);
        } else {
            $result .= $body;
        }
        
        return $result;
    }
}