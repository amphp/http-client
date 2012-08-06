<?php

namespace Artax\Http;

use DomainException,
    RuntimeException,
    InvalidArgumentException,
    Artax\Http\Exceptions\MessageValidationException,
    Artax\Http\Exceptions\MessageParseException;

class MutableStdRequest extends StdRequest implements MutableRequest {
    
    public function __construct(){}
    
    /**
     * @param string $uri
     */
    public function setUri($uri) {
        $this->uri = $uri instanceof Uri ? $uri : $this->buildUriFromString($uri);
        $this->queryParameters = $this->parseParametersFromString($this->uri->getQuery());
    }
    
    /**
     * @return string
     */
    public function getUri() {
        return $this->uri ? $this->uri->__toString() : null;
    }
    
    /**
     * @param string $httpMethodVerb
     */
    public function setMethod($httpMethodVerb) {
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5.1.1
        // "The method is case-sensitive"
        $this->method = strtoupper($httpMethodVerb);
        
        if ($this->body && $this->hasFormEncodedBody() && $this->acceptsEntityBody()) {
            $this->bodyParameters = $this->parseParametersFromString($this->getBody());
        } else {
            $this->bodyParameters = array();
        }
    }
    
    /**
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        $this->httpVersion = $httpVersion;
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
        $this->assignAllHeaders($iterable);
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
     * @param string $body
     * @return void
     */
    public function setBody($body) {
        $this->body = $body;
        
        if ($body && $this->acceptsEntityBody() && $this->hasFormEncodedBody()) {
            $this->bodyParameters = $this->parseParametersFromString($this->getBody());
        } else {
            $this->bodyParameters = array();
        }
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
        $this->method = null;
        $this->uri = null;
        $this->httpVersion = '1.1';
        $this->headers = array();
        $this->body = null;
        $this->queryParameters = array();
        $this->bodyParameters = array();
    }
    
    /**
     * Populate response values from a raw HTTP message
     * 
     * There is no way to determine from a raw HTTP message that specifies the URI as an absolute
     * path in the request line which protocol was used for the request (http|https). If this 
     * situation occurs, the parser will assume "http." If the protocol is pertinent, use the
     * full URI request line form:
     * 
     *     GET http://www.w3.org/pub/WWW/TheProject.html HTTP/1.1
     * 
     * 
     * @param string $rawMessage
     * @return void
     * @throws Artax\Http\Exceptions\MessageParseException
     */
    public function populateFromRawMessage($rawMessage) {
        $this->clearAll();
        
        $requestLineAndEverythingElse = explode("\r\n", $rawMessage, 2);
        if (2 !== count($requestLineAndEverythingElse)) {
            throw new MessageParseException(
                'Invalid HTTP request message specified for parsing'
            );
        }
        
        list($requestLine, $headersAndEverythingElse) = $requestLineAndEverythingElse;
        
        $requestLinePattern = ',^([a-zA-Z]+) ([^\s]+) HTTP/(\d+\.\d+)$,';
        if (!preg_match($requestLinePattern, $requestLine, $matches)) {
            throw new MessageParseException(
                'Invalid HTTP request message specified for parsing'
            );
        }
        
        $this->setMethod($matches[1]);
        $this->setHttpVersion($matches[3]);
        $uri = $matches[2];
        // URI assigned after headers determined because we need a HOST for URI path resolution
        
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
        
        if (parse_url($uri, PHP_URL_SCHEME)) {
            $this->setUri($uri);
        } elseif ($this->hasHeader('Host')) {
            $this->setUri('http://' . $this->getHeader('Host') . $uri);
        } else {
            throw new MessageParseException(
                'Invalid HTTP request message: A Host header must be specified when a URI path' .
                "is placed in the request line: $uri"
            );
        }
        
        $this->setBody($body);
    }
    
    /**
     * @param Request $request
     */
    public function populateFromRequest(Request $request) {
        $this->setHttpVersion($request->getHttpVersion());
        $this->setMethod($request->getMethod());
        $this->setUri($request->getRawUri());
        $this->setAllHeaders($request->getAllHeaders());
        $this->setBody($request->getBody());
    }
    
    /**
     * Returns a fully stringified HTTP request message
     * 
     * If the current request properties do not pass validation an empty string is returned.
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
    
    /**
     * @throws Artax\Http\Exceptions\MessageValidationException
     */
    public function validateMessage() {
        if (!$this->uri) {
            throw new MessageValidationException(
                'No request URI specified'
            );
        }
        if (!$this->method) {
            throw new MessageValidationException(
                'No HTTP method verb specified'
            );
        }
        if ($this->body && !$this->acceptsEntityBody()) {
            throw new MessageValidationException(
                "{$this->method} HTTP requests may not contain an entity body"
            );
        }
    }
}
