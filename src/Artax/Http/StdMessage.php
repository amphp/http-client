<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException,
    Artax\Http\Exceptions\MessageParseException;

abstract class StdMessage implements Message {

    /**
     * @var array
     */
    protected $headers = array();
    
    /**
     * @var mixed
     */
    protected $body;
    
    /**
     * @var string
     */
    protected $cachedBodyFromStream;

    /**
     * @var string
     */
    protected $httpVersion = '1.1';
    
    /**
     * Assign an entity body to the HTTP message
     * 
     * @param mixed $bodyStringOrResource A string or stream resource
     * @return void
     */
    public function setBody($bodyStringOrResource) {
        $this->body = $bodyStringOrResource;
        $this->cachedBodyFromStream = null;
    }
    
    /**
     * Retrieve the HTTP message entity body in string form
     * 
     * If a resource stream is assigned to the body property, its entire contents will be read into
     * memory and returned as a string. To access the stream resource directly without buffering
     * its contents, use Message::getBodyStream().
     * 
     * @return string
     */
    public function getBody() {
        if (!is_resource($this->body)) {
            return (string) $this->body;
        } elseif (!is_null($this->cachedBodyFromStream)) {
            return $this->cachedBodyFromStream;
        } else {
            $this->cachedBodyFromStream = stream_get_contents($this->body);
            rewind($this->body);
            return $this->cachedBodyFromStream;
        }
    }
    
    /**
     * Retrieve the HTTP message entity body as a stream resource (if available)
     * 
     * If the assigned entity body is not a stream, null is returned.
     * 
     * @return resource
     */
    public function getBodyStream() {
        return is_resource($this->body) ? $this->body : null;
    }

    /**
     * Does the HTTP message contain the specified header field?
     * 
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
     * Retrieve the value of the specified header field
     * 
     * @param string $headerName
     * @return string
     * @throws RuntimeException
     * @todo Determine the best exception to throw
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
     * Retrieve a traversable key-value list of header fields and their values
     * 
     * @return array
     */
    public function getAllHeaders() {
        return $this->headers;
    }
    
    /**
     * Assign a message header
     * 
     * @param string $headerName
     * @param string $value
     * @return void
     */
    public function setHeader($headerName, $value) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $normalizedHeader = rtrim(strtoupper($headerName), ': ');
        $this->headers[$normalizedHeader] = $value;
    }
    
    /**
     * Assign all header values from a traversable key-value list of header fields and their values
     * 
     * @param mixed $iterable
     * @return void
     * @throws InvalidArgumentException
     */
    public function setAllHeaders($iterable) {
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
            $this->setHeader($headerName, $value);
        }
    }
    
    /**
     * @param string $rawHeaderStr
     * @return void
     * @throws InvalidArgumentException
     * @todo Determine if generic "InvalidFormatException" might be a better option
     */
    public function setRawHeader($rawHeaderStr) {
        // rfc2616-4.2:
        // "Header fields can be extended over multiple lines by preceding each extra line with at
        // least one SP or HT ... The field-content does not include any leading or trailing LWS: 
        // linear white space occurring before the first non-whitespace character of the field-value
        // or after the last non-whitespace character of the field-value. Such leading or trailing 
        // LWS MAY be removed without changing the semantics of the field value. Any LWS that occurs
        // between field-content MAY be replaced with a single SP before interpreting the field 
        // value or forwarding the message downstream."
        if (strstr($rawHeaderStr, "\r\n")) {
            $normalizedHeaderStr = rtrim(preg_replace(",\r\n[ \t]+,", ' ', $rawHeaderStr));
        } else {
            $normalizedHeaderStr = rtrim($rawHeaderStr);
        }
        
        if (!preg_match(",^([^\s:]+):[ \t]*(.+)$,", $normalizedHeaderStr, $match)) {
            throw new InvalidArgumentException(
                "Invalid raw header: $rawHeaderStr"
            );
        }
        
        $this->setHeader($match[1], $match[2]);
    }
    
    /**
     * Set all message headers from a raw string -- will clear all previously assigned headers.
     * 
     * @param string $rawHeaderStr
     * @return void
     * @throws MessageParseException
     */
    public function setAllRawHeaders($rawHeaderStr) {
        $this->headers = array();
        
        $normalized = preg_replace(",\r\n[ \t]+,", ' ', $rawHeaderStr);
        
        if (!preg_match_all(",^([^\s:]+):[ \t]*(.+)$,m", $normalized, $matches, PREG_SET_ORDER)) {
            throw new MessageParseException(
                "Invalid raw headers: no valid headers found"
            );
        }
        
        foreach ($matches as $match) {
            $header = $match[1];
            $value  = rtrim($match[2]);
            if ($this->hasHeader($header)) {
                $this->setHeader($header, $this->getHeader($header) . ',' . $value);
            } else {
                $this->setHeader($header, $value);
            }
        }
    }
    
    /**
     * Remove the specified header from the message
     * 
     * @param string $headerName
     * @return void
     */
    public function removeHeader($headerName) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        unset($this->headers[$capsHeader]);
    }
    
    /**
     * Clear all assigned headers from the message
     * 
     * @return void
     */
    public function clearAllHeaders() {
        $this->headers = array();
    }
    
    /**
     * Retrieve the numerical HTTP version adhered to by the message (without the "HTTP/" prefix)
     * 
     * @return string
     */
    public function getHttpVersion() {
        return $this->httpVersion;
    }
    
    /**
     * Assign the HTTP version adhered to by the message (without the "HTTP/" prefix)
     * 
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        $this->httpVersion = $httpVersion;
    }
}
