<?php

namespace Artax\Http;

use StdClass,
    Traversable,
    LogicException,
    RuntimeException,
    InvalidArgumentException,
    Artax\Events\Mediator;

class StdResponse implements Response {

    /**
     * @var string
     */
    private $httpVersion = '1.1';

    /**
     * @var string
     */
    private $statusCode;

    /**
     * @var string
     */
    private $statusDescription;

    /**
     * @var array
     */
    private $headers = array();
    
    /**
     * @var string
     */
    private $body = '';

    /**
     * @var bool
     */
    private $wasSent = false;
    
    /**
     * @param string $rawStartLineStr
     * @return void
     * @throws InvalidArgumentException
     * @todo Determine if generic "InvalidFormatException" might be a better option
     */
    public function setStartLine($rawStartLineStr) {
        // Conforms to Start-Line specification in rfc2616-sec6.1
        $pattern = ',^HTTP/(\d+\.\d+) (\d{3}) (.+)$,';
        if (!preg_match($pattern, $rawStartLineStr, $match)) {
            throw new InvalidArgumentException(
                "Invalid HTTP start line: $rawStartLineStr"
            );
        }
        
        $this->httpVersion = $match[1];
        $this->statusCode = $match[2];
        $this->statusDescription = $match[3];
    }
    
    /**
     * @return string The HTTP version number (not prefixed by `HTTP/`)
     */
    public function getHttpVersion() {
        return $this->httpVersion;
    }

    /**
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        $this->httpVersion = $httpVersion;
    }

    /**
     * @return int
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @param string $httpStatusCode
     * @return void
     */
    public function setStatusCode($httpStatusCode) {
        $this->statusCode = $httpStatusCode;
    }

    /**
     * @return string
     */
    public function getStatusDescription() {
        return $this->statusDescription;
    }

    /**
     * @param string $httpStatusDescription
     * @return void
     */
    public function setStatusDescription($httpStatusDescription) {
        $this->statusDescription = $httpStatusDescription;
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
     * @param string $headerName
     * @return bool
     */
    public function hasHeader($headerName) {
        // Headers are case-insensitive:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        return isset($this->headers[$capsHeader]) || array_key_exists($capsHeader, $this->headers);
    }

    /**
     * @return array
     */
    public function getAllHeaders() {
        return $this->headers;
    }

    /**
     * @param string $headerName
     * @param string $value
     * @return void
     */
    public function setHeader($headerName, $value) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        $this->headers[$capsHeader] = $value;
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
            $normalized = rtrim(preg_replace(",\r\n[ \t]+,", ' ', $rawHeaderStr));
        } else {
            $normalized = rtrim($rawHeaderStr);
        }
        
        $pattern = ',^([^\s:]+):[ \t]*(.+)$,';
        if (!preg_match($pattern, $normalized, $match)) {
            throw new InvalidArgumentException(
                "Invalid raw header: $rawHeaderStr"
            );
        }
        
        $this->setHeader($match[1], $match[2]);
    }
    
    /**
     * @param mixed $iterable
     * @return void
     * @throws InvalidArgumentException
     */
    public function setAllHeaders($iterable) {
        if (!($iterable instanceof Traversable
            || $iterable instanceof StdClass
            || is_array($iterable)
        )) {
            throw new InvalidArgumentException(
                'Argument 1 passed to '.get_class($this).'::setAllHeaders must '
                .'be an array, StdClass or Traversable object'
            );
        }
        foreach ($iterable as $headerName => $value) {
            $this->setHeader($headerName, $value);
        }
    }
    
    /**
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
     * @return string
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @param string $body
     * @return void
     * @notifies sys.response.set-body(StdResponse $response)
     */
    public function setBody($bodyString) {
        $this->body = $bodyString;
    }

    /**
     * Formats and sends all headers prior to outputting the message body.
     * @return void
     * @throws LogicException
     */
    public function send() {
        if (!$this->statusCode) {
            throw new LogicException('Cannot send response without an assigned HTTP status code');
        }
        
        $headerStr = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode() . ' ' .
            $this->getStatusDescription();
        
        header($headerStr);
        
        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value);
        }

        echo $this->body;
        
        $this->wasSent = true;
    }

    /**
     * @return bool
     */
    public function wasSent() {
        return $this->wasSent;
    }
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode();
        $msg.= ' ' . $this->getStatusDescription() . "\r\n";
        
        $headerArr = $this->getAllHeaders();
        $headers = array_combine(
            array_map('strtoupper', array_keys($headerArr)),
            array_values($headerArr)
        );
        
        foreach ($headers as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        
        $msg.= "\r\n" . $this->getBody();
        
        return $msg;
    }
    
    /**
     * Reset the object to its original state
     * 
     * @return void
     */
    public function clearAssignedValues() {
        $this->httpVersion = '1.1';
        $this->statusCode = null;
        $this->statusDescription = null;
        $this->headers = array();
        $this->body = '';
        $this->wasSent = false;
    }
    
    /**
     * Populate response values from a raw HTTP message
     * 
     * @param string $rawMessage
     * @return void
     * @throws InvalidArgumentException
     */
    public function populateFromRawMessage($rawMessage) {
        $this->clearAssignedValues();
        
        $startLineAndEverythingElse = explode("\r\n", $rawMessage, 2);
        if (2 !== count($startLineAndEverythingElse)) {
            throw new InvalidArgumentException(
                'Invalid HTTP response message specified for parsing'
            );
        }
        
        list($startLine, $headersAndEverythingElse) = $startLineAndEverythingElse;
        
        try {
            $this->setStartLine($startLine);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                'Invalid HTTP response message specified for parsing'
            );
        }
        
        $headersAndBody = explode("\r\n\r\n", $headersAndEverythingElse, 2);
        $headers = $headersAndBody[0];
        $body = isset($headersAndBody[1]) ? $headersAndBody[1] : '';
        
        $normalizedHeaders = preg_replace(",\r\n[ \t]+,", ' ', $headers);
        preg_match_all(',([^\s:]+):[ \t]*(.+),', $normalizedHeaders, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $header = $match[1];
            $value  = rtrim($match[2]);
            if ($this->hasHeader($header)) {
                $this->setHeader($header, $this->getHeader($header) . ',' . $value);
            } else {
                $this->setHeader($header, $value);
            }
        }
        
        $this->setBody($body);
    }
}
