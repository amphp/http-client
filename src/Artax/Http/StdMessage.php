<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException,
    Spl\TypeException,
    Spl\ValueException,
    Artax\Http\Exceptions\MessageParseException;

abstract class StdMessage implements Message {

    /**
     * @var Artax\Http\HeaderCollection
     */
    protected $headers;
    
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
     * @return void
     */
    public function __construct() {
        $this->headers = new HeaderCollection();
    }
    
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
     * memory, cached for future reads and returned in string form. To access the stream resource
     * directly without buffering its contents, use Message::getBodyStream().
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
     * Assign the HTTP version adhered to by the message (without the "HTTP/" prefix)
     * 
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        $this->httpVersion = $httpVersion;
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
     * Does the HTTP message contain the specified header field?
     * 
     * @param string $headerName
     * @return bool
     */
    public function hasHeader($headerName) {
        return $this->headers->hasHeader($headerName);
    }

    /**
     * Retrieve the value of the specified header field
     * 
     * @param string $headerName
     * @return string
     * @throws Spl\DomainException
     * @todo Determine the best exception to throw
     */
    public function getHeader($headerName) {
        return $this->headers->getHeader($headerName);
    }

    /**
     * Retrieve an array of header fields and their associated values
     * 
     * Headers with multiple values will be returned as an array. All others will be in string form.
     * 
     * @return array
     */
    public function getHeadersArray() {
        $return = array();
        foreach ($this->headers as $header) {
            $key = $header->getName();
            $return[$key] = count($header) == 1 ? $header->getValue() : $header->getValueArray();
        }
        return $return;
    }
    
    /**
     * Retrieve the message headers as they would appear in an HTTP message
     * 
     * @return string
     */
    public function getRawHeadersString() {
        return $this->headers->__toString();
    }
    
    /**
     * Assign a message header (replaces previous assignment)
     * 
     * @param string $headerName
     * @param string $value
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function setHeader($headerName, $value) {
        $this->headers->setHeader($headerName, $value);
    }
    
    /**
     * Assign header or append to a matching previously assigned header if already set
     * 
     * If the header is already assigned, the new value will be appended to the current value
     * using a leading comma. Otherwise, this method behaves the same as `setHeader()`.
     * 
     * @param string $headerName
     * @param string $value
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function appendHeader($headerName, $value) {
        $this->headers->appendHeader($headerName, $value);
    }
    
    /**
     * Clears previously assigned values and adds new headers from a key-value traversable
     * 
     * Any previously assigned headers are cleared.
     * 
     * @param mixed $iterable
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function setAllHeaders($iterable) {
        if (!$this->validateIterable($iterable)) {
            $type = is_object($iterable) ? get_class($iterable) : gettype($iterable);
            throw new TypeException(
                get_class($this) . '::setAllHeaders expects an array, StdClass or Traversable ' .
                "at Argument 1: $type specified"
            );
        }
        
        $this->headers->removeAllHeaders();
        
        foreach ($iterable as $headerName => $value) {
            $this->headers->setHeader($headerName, $value);
        }
    }
    
    /**
     * @param mixed $iterable
     * @return bool
     */
    protected function validateIterable($iter) {
        return $iter instanceof Traversable || $iter instanceof StdClass || is_array($iter);
    }
    
    /**
     * Assign or append headers from a key-value traversable
     * 
     * @param mixed $iterable
     * @return void
     * @throws Spl\TypeException
     */
    public function appendAllHeaders($iterable) {
        if (!$this->validateIterable($iterable)) {
            $type = is_object($iterable) ? get_class($iterable) : gettype($iterable);
            throw new TypeException(
                get_class($this) . '::appendAllHeaders expects an array, StdClass or Traversable ' .
                "at Argument 1: $type specified"
            );
        }
        
        foreach ($iterable as $headerName => $value) {
            $this->headers->appendHeader($headerName, $value);
        }
    }
    
    /**
     * @param string $rawHeaderStr
     * @return void
     * @throws Spl\ValueException
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
        
        if (!preg_match(",^([^\s:]+):[ \t]*(.+)?$,", $normalizedHeaderStr, $match)) {
            throw new ValueException(
                "Invalid raw header: $rawHeaderStr"
            );
        }
        
        $header = $match[1];
        $value = isset($match[2]) ? $match[2] : '';
        $this->headers->setHeader($header, $value);
    }
    
    /**
     * Set all message headers from a raw string
     * 
     * Any previously assigned headers are cleared.
     * 
     * @param string $rawHeaderStr
     * @return void
     * @throws Spl\ValueException
     */
    public function setAllRawHeaders($rawHeaderStr) {
        $this->headers->removeAllHeaders();
        
        $normalized = preg_replace(",\r\n[ \t]+,", ' ', $rawHeaderStr);
        
        if (!preg_match_all(",^([^\s:]+):[ \t]*(.+)?$,m", $normalized, $matches, PREG_SET_ORDER)) {
            throw new ValueException(
                "Invalid raw headers: no valid headers found"
            );
        }
        
        foreach ($matches as $match) {
            $header = $match[1];
            $value = isset($match[2]) ? rtrim($match[2]) : '';
            $this->headers->appendHeader($header, $value);
        }
    }
    
    /**
     * Remove the specified header from the message
     * 
     * @param string $headerName
     * @return void
     */
    public function removeHeader($headerName) {
        $this->headers->removeHeader($headerName);
    }
    
    /**
     * Clear all assigned headers from the message
     * 
     * @return void
     */
    public function removeAllHeaders() {
        $this->headers->removeAllHeaders();
    }
}
