<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException,
    Spl\TypeException,
    Spl\ValueException,
    Spl\DomainException;

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
     * memory, cached for future reads and returned in string form. To access the stream resource
     * directly without buffering its contents, use Message::getBodyStream() instead.
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
     * If the assigned entity body is not a stream, NULL is returned.
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
     * @return void
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
     * Has the specified header been assigned to the message?
     *
     * @param string $headerName
     * @return bool
     */
    public function hasHeader($headerName) {
        $normalizedHeaderName = $this->normalizeHeaderName($headerName);
        return isset($this->headers[$normalizedHeaderName]);
    }

    private function normalizeHeaderName($headerName) {
        return strtoupper(rtrim($headerName, ': '));
    }

    /**
     * Retrieve the string value of the specified header
     *
     * If multiple values are assigned to the specified header, they will be concatenated together
     * and delimited by commas.
     *
     * @param string $headerName
     * @throws \Spl\DomainException
     * @return string
     */
    public function getHeader($headerName) {
        if ($this->hasHeader($headerName)) {
            $normalizedHeaderName = $this->normalizeHeaderName($headerName);
            $header = $this->headers[$normalizedHeaderName];
            return $header->getValue();
        }

        throw new DomainException(
            "The specified header $headerName does not exist"
        );
    }

    /**
     * Retrieve an array of all values assigned to the specified header
     *
     * @param string $headerName
     * @throws \Spl\DomainException
     * @return array
     */
    public function getHeaderValueArray($headerName) {
        if ($this->hasHeader($headerName)) {
            $normalizedHeaderName = $this->normalizeHeaderName($headerName);
            $header = $this->headers[$normalizedHeaderName];
            return $header->getValueArray();
        } else {
            throw new DomainException(
                "The specified header $headerName does not exist"
            );
        }
    }

    /**
     * Does the specified header have multiple values assigned to it?
     *
     * @param string $headerName
     * @throws \Spl\DomainException
     * @return bool
     */
    public function isMultiHeader($headerName) {
        $normalizedHeaderName = $this->normalizeHeaderName($headerName);

        if (isset($this->headers[$normalizedHeaderName])) {
            return count($this->headers[$normalizedHeaderName]) > 1;
        } else {
            throw new DomainException(
                "The specified header $headerName does not exist"
            );
        }
    }

    /**
     * Retrieve an array of header fields and their associated values
     *
     * Headers with multiple values will be returned as an array. All others will be in string form.
     *
     * @return array
     */
    public function getAllHeaders() {
        $return = array();
        foreach ($this->headers as $header) {
            $key = $header->getName();
            $return[$key] = count($header) == 1 ? $header->getValue() : $header->getValueArray();
        }
        return $return;
    }

    /**
     * Retrieve all message headers as they would appear in a raw HTTP message
     *
     * @return string
     */
    public function getRawHeaders() {
        $str = '';
        foreach ($this->headers as $headerObj) {
            $str .= $headerObj->__toString();
        }
        return $str;
    }

    /**
     * @param string $headerName
     * @param mixed $value A string or single-dimensional array of strings
     * @throws \Spl\TypeException On invalid header value
     * @return void
     */
    public function setHeader($headerName, $value) {
        $header = new Header($headerName, $value);
        $normalizedHeaderName = $this->normalizeHeaderName($headerName);
        $this->headers[$normalizedHeaderName] = $header;
    }

    /**
     * Add a header to the collection
     *
     * If the header field already exists, the value will be appended to the existing header.
     *
     * @param string $headerName
     * @param mixed $value A string or single-dimensional array of strings
     * @throws \Spl\TypeException On invalid header value
     * @return void
     */
    public function appendHeader($headerName, $value) {
        $normalizedHeaderName = $this->normalizeHeaderName($headerName);
        if (isset($this->headers[$normalizedHeaderName])) {
            $header = $this->headers[$normalizedHeaderName];
            $header->appendValue($value);
        } else {
            $this->setHeader($headerName, $value);
        }
    }

    /**
     * Clears previously assigned values and adds new headers from a key-value traversable
     *
     * All previously assigned headers are cleared.
     *
     * @param mixed $iterable
     * @throws \Spl\TypeException On invalid header value
     * @return void
     */
    public function setAllHeaders($iterable) {
        if (!$this->isValidIterable($iterable)) {
            $type = is_object($iterable) ? get_class($iterable) : gettype($iterable);
            throw new TypeException(
                get_class($this) . '::setAllHeaders expects an array, StdClass or Traversable ' .
                "at Argument 1: $type specified"
            );
        }

        $this->removeAllHeaders();

        foreach ($iterable as $headerName => $value) {
            $this->setHeader($headerName, $value);
        }
    }

    protected function isValidIterable($iter) {
        return $iter instanceof Traversable || $iter instanceof StdClass || is_array($iter);
    }

    /**
     * Assign or append headers from a traversable without clearing previously assigned values
     *
     * @param mixed $iterable
     * @throws \Spl\TypeException On invalid header value
     * @return void
     */
    public function appendAllHeaders($iterable) {
        if (!$this->isValidIterable($iterable)) {
            $type = is_object($iterable) ? get_class($iterable) : gettype($iterable);
            throw new TypeException(
                get_class($this) . '::appendAllHeaders expects an array, StdClass or Traversable ' .
                "at Argument 1: $type specified"
            );
        }

        foreach ($iterable as $headerName => $value) {
            $this->appendHeader($headerName, $value);
        }
    }

    /**
     * Assign or replace an existing header from a raw string
     *
     * @param string $rawHeaderStr
     * @throws \Spl\ValueException On invalid raw header string
     * @return void
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
        $this->setHeader($header, $value);
    }

    /**
     * Set all message headers from a raw string
     *
     * All previously assigned headers are cleared when headers are assigned with this method.
     *
     * @param string $rawHeaderStr
     * @throws \Spl\ValueException
     * @return void
     */
    public function setAllRawHeaders($rawHeaderStr) {
        $this->removeAllHeaders();

        $normalized = trim(preg_replace(",\r\n[ \t]+,", ' ', $rawHeaderStr));

        if (!preg_match_all(",^([^\s:]+):[ \t]*(.+)?$,m", $normalized, $matches, PREG_SET_ORDER)) {
            throw new ValueException(
                "Invalid raw headers: no valid headers found"
            );
        }

        foreach ($matches as $match) {
            $header = $match[1];
            $value = isset($match[2]) ? rtrim($match[2]) : '';
            $this->appendHeader($header, $value);
        }
    }

    /**
     * Remove the specified header from the message
     *
     * @param string $headerName
     * @return void
     */
    public function removeHeader($headerName) {
        $normalizedHeaderName = $this->normalizeHeaderName($headerName);
        unset($this->headers[$normalizedHeaderName]);
    }

    /**
     * Clear all assigned headers from the message
     *
     * @return void
     */
    public function removeAllHeaders() {
        $this->headers = array();
    }
}