<?php

namespace Artax\Http;

use StdClass,
    Traversable,
    InvalidArgumentException,
    Artax\Http\Exceptions\MessageParseException;

class MutableStdResponse extends StdResponse implements MutableResponse {
    
    public function __construct() {}
    
    /**
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        $this->httpVersion = $httpVersion;
    }

    /**
     * @param string $httpStatusCode
     * @return void
     */
    public function setStatusCode($httpStatusCode) {
        $this->statusCode = $httpStatusCode;
    }

    /**
     * @param string $httpStatusDescription
     * @return void
     */
    public function setStatusDescription($httpStatusDescription) {
        $this->statusDescription = $httpStatusDescription;
    }

    /**
     * @param string $rawStartLineStr
     * @return void
     * @throws MessageParseException
     * @todo Determine if generic "InvalidFormatException" might be a better option
     */
    public function setStartLine($rawStartLineStr) {
        // Conforms to Start-Line specification in rfc2616-sec6.1
        $pattern = ',^HTTP/(\d+\.\d+) (\d{3}) (.+)$,';
        if (!preg_match($pattern, $rawStartLineStr, $match)) {
            throw new MessageParseException(
                "Invalid HTTP start line: $rawStartLineStr"
            );
        }
        
        $this->httpVersion = $match[1];
        $this->statusCode = $match[2];
        $this->statusDescription = $match[3];
    }

    /**
     * @param string $headerName
     * @param string $value
     * @return void
     */
    public function setHeader($headerName, $value) {
        $this->assignHeader($headerName, $value);
    }
    
    /**
     * @param mixed $iterable
     * @return void
     * @throws InvalidArgumentException
     */
    public function setAllHeaders($iterable) {
        $this->assignAllHeaders($iterable);
    }
    
    /**
     * Set a message header from a raw string -- will replace a previously assigned header value.
     * 
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
        
        $pattern = ",^([^\s:]+):[ \t]*(.+)$,";
        if (!preg_match($pattern, $normalized, $match)) {
            throw new MessageParseException(
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
     * @param string $headerName
     * @return void
     */
    public function removeHeader($headerName) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $normalized = strtoupper($headerName);
        unset($this->headers[$normalized]);
    }

    /**
     * @param string $body
     * @return void
     */
    public function setBody($bodyString) {
        $this->body = $bodyString;
    }
    
    public function removeBody() {
        $this->body = null;
    }
    
    /**
     * Reset the object to its original state
     * 
     * @return void
     */
    public function clearAll() {
        $this->httpVersion = '1.1';
        $this->statusCode = null;
        $this->statusDescription = null;
        $this->headers = array();
        $this->body = null;
    }
    
    /**
     * Populate response values from a raw HTTP message
     * 
     * @param string $rawMessage
     * @return void
     * @throws Artax\Http\Exceptions\MessageParseException
     */
    public function populateFromRawMessage($rawMessage) {
        $this->clearAll();
        
        $startLineAndEverythingElse = explode("\r\n", $rawMessage, 2);
        if (2 !== count($startLineAndEverythingElse)) {
            throw new MessageParseException(
                'Invalid HTTP response message specified for parsing'
            );
        }
        
        list($startLine, $headersAndEverythingElse) = $startLineAndEverythingElse;
        $this->setStartLine($startLine);
        
        $headersAndBody = explode("\r\n\r\n", $headersAndEverythingElse, 2);
        $this->setAllRawHeaders($headersAndBody[0]);
        if (isset($headersAndBody[1])) {
            $this->setBody($headersAndBody[1]);
        }
    }
    
    /**
     * @param Response $response
     */
    public function populateFromResponse(Response $response) {
        $this->setHttpVersion($response->getHttpVersion());
        $this->setStatusCode($response->getStatusCode());
        $this->setStatusDescription($response->getStatusDescription());
        $this->setAllHeaders($response->getAllHeaders());
        $this->setBody($response->getBody());
    }
    
    /**
     * Returns a fully stringified HTTP response message
     * 
     * If the current response properties do not pass validation an empty string is returned.
     * 
     * Messages generated in accordance with RFC2616 section 5:
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
     * 
     * @return string
     */
    public function __toString() {
        try {
            $this->validateMessage();
        } catch (MessageValidationException $e) {
            return '';
        }
        
        return parent::__toString();
    }
    
    public function validateMessage() {
        
    }
}
