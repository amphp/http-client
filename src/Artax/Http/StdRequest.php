<?php

namespace Artax\Http;

use DomainException,
    RuntimeException,
    InvalidArgumentException;

class StdRequest implements FormEncodableRequest {
    
    /**
     * @var StdUri
     */
    protected $uri;
    
    /**
     * @var string
     */
    protected $method;
    
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
    protected $httpVersion;
    
    /**
     * @var array
     */
    protected $queryParameters;
    
    /**
     * @var array
     */
    protected $bodyParameters = array();

    /**
     * @param mixed $uri A valid URI string or instance of Artax\Http\Uri
     * @param string $method
     * @param array $headers
     * @param string $body
     * @param string $httpVersion
     * @return void
     * @throws InvalidArgumentException
     */
    public function __construct($uri, $method, $headers = array(), $body = '', $httpVersion = '1.1') {
        $this->uri = $uri instanceof Uri ? $uri : $this->buildUriFromString($uri);
        $this->method = strtoupper($method);
        
        if ($headers) {
            $this->assignAllHeaders($headers);
        }
        
        $this->body = $this->acceptsEntityBody() ? $body : '';
        $this->httpVersion = $httpVersion;
        
        $this->queryParameters = $this->parseParametersFromString($this->uri->getQuery());
        
        if ($this->hasFormEncodedBody() && $this->acceptsEntityBody()) {
            $this->bodyParameters = $this->parseParametersFromString($this->getBody());
        } else {
            $this->bodyParameters = array();
        }
    }
    
    /**
     * @param string $uri
     * @return Artax\Http\StdUri
     */
    protected function buildUriFromString($uri) {
        try {
            return new StdUri($uri);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                'Invalid URI specified at Argument 1 in ' . get_class($this) . '::__construct: ' .
                "$uri. Please specify a valid URI string or instance of Artax\\Http\\Uri",
                null,
                $e
            );
        }
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
            $normalized[rtrim(strtoupper($header), ': ')] = $value;
        }
        
        return $normalized;
    }
    
    /**
     * @param string $paramString
     * @return array
     */
    protected function parseParametersFromString($paramString) {
        parse_str($paramString, $parameters);
        return array_map('urldecode', $parameters);
    }
    
    /**
     * @return bool
     */
    protected function hasFormEncodedBody() {
        if (!$this->hasHeader('Content-Type')) {
            return false;
        } else {
            $contentType = strtolower($this->getHeader('Content-Type'));
        }
        
        return ($contentType == 'application/x-www-form-urlencoded');
    }
    
    /**
     * @param string $httpMethod
     * @return bool
     */
    protected function acceptsEntityBody() {
        $methodsDisallowingEntityBody = array('GET', 'HEAD', 'DELETE', 'TRACE', 'CONNECT');
        return !in_array($this->getMethod(), $methodsDisallowingEntityBody);
    }

    /**
     * @return string The HTTP version, not prefixed by `HTTP/`
     */
    public function getHttpVersion() {
        return $this->httpVersion;
    }
    
    /**
     * @return string
     */
    public function getUri() {
        return $this->uri->__toString();
    }
    
    /**
     * @return string
     */
    public function getRawUri() {
        return $this->uri->getRawUri();
    }
    
    /**
     * @return string
     */
    public function getScheme() {
        return $this->uri->getScheme();
    }
    
    /**
     * @return string
     */
    public function getHost() {
        return $this->uri->getHost();
    }
    
    /**
     * @return string
     */
    public function getPort() {
        return $this->uri->getPort();
    }
    
    /**
     * @return string
     */
    public function getPath() {
        return $this->uri->getPath();
    }
    
    /**
     * @return string
     */
    public function getQuery() {
        return $this->uri->getQuery();
    }
    
    /**
     * @return string
     */
    public function getFragment() {
        return $this->uri->getFragment();
    }
    
    /**
     * @return string
     */
    public function getAuthority() {
        return $this->uri->getAuthority();
    }
    
    /**
     * @return string
     */
    public function getRawAuthority() {
        return $this->uri->getRawAuthority();
    }
    
    /**
     * @return string
     */
    public function getUserInfo() {
        return $this->uri->getUserInfo();
    }
    
    /**
     * @return string
     */
    public function getRawUserInfo() {
        return $this->uri->getRawUserInfo();
    }

    /**
     * @return string The HTTP method, upper-cased.
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @param string $header
     * @return string
     * @throws RuntimeException if the header doesn't exist.
     * @todo Figure out the best exception to throw.
     */
    public function getHeader($header) {
        if (!$this->hasHeader($header)) {
            throw new RuntimeException();
        }
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $upHeader = strtoupper($header);
        return $this->headers[$upHeader];
    }

    /**
     * @param string $header
     * @return bool
     */
    public function hasHeader($header) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $upHeader = strtoupper($header);
        return array_key_exists($upHeader, $this->headers);
    }

    /**
     * @return array
     */
    public function getAllHeaders() {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getBody() {
        return $this->body;
    }
    
    /**
     * @param string $parameter
     * @return bool
     */
    public function hasQueryParameter($parameter) {
        return isset($this->queryParameters[$parameter]);
    }
    
    /**
     * @param string $parameter
     * @return string
     * @todo Determine appropriate exception for invalid parameter request
     */
    public function getQueryParameter($parameter) {
        if (!$this->hasQueryParameter($parameter)) {
            throw new RuntimeException;
        }
        return $this->queryParameters[$parameter];
    }
    
    /**
     * @return array
     */
    public function getAllQueryParameters() {
        return $this->queryParameters;
    }
    
    /**
     * @param string $parameter
     * @return bool
     */
    public function hasBodyParameter($parameter) {
        return isset($this->bodyParameters[$parameter]);
    }
    
    /**
     * @param string $parameter
     * @return string
     * @todo Determine appropriate exception for invalid parameter request
     */
    public function getBodyParameter($parameter) {
        if (!$this->hasBodyParameter($parameter)) {
            throw new RuntimeException;
        }
        return $this->bodyParameters[$parameter];
    }
    
    /**
     * @return array
     */
    public function getAllBodyParameters() {
        return $this->bodyParameters;
    }
    
    /**
     * Returns a fully stringified HTTP request message to be sent to an HTTP/1.1 server
     * 
     * Messages generated in accordance with RFC2616 section 5:
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
     * 
     * @return string
     */
    public function __toString() {
        if (strcmp('CONNECT', $this->getMethod())) {
            return $this->buildMessage();
        } else {
            return $this->buildConnectMessage();
        }
    }
    
    /**
     * @return string
     */
    protected function buildMessage() {
        $msg = $this->getMethod() . ' ' . $this->getPath();
        if ($queryStr = $this->getQuery()) {
            $msg.= "?$queryStr";
        }
        $msg.= ' HTTP/' . $this->getHttpVersion() . "\r\n";
        $msg.= 'HOST: ' . $this->getAuthority() . "\r\n";
        
        if ($body = $this->getBody()) {
            $msg.= 'CONTENT-LENGTH: ' . strlen($body) . "\r\n";
        }
        
        $headers = $this->getAllHeaders();
        unset($headers['HOST']);
        unset($headers['CONTENT-LENGTH']);
        
        foreach ($headers as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        
        $msg.= "\r\n$body";
        
        return $msg;
    }
    
    /**
     * @return string
     */
    protected function buildConnectMessage() {
        $msg = 'CONNECT ' . $this->getRawAuthority() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion() . "\r\n";
        
        foreach ($this->getAllHeaders() as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        $msg.= "\r\n";
        
        return $msg;
    }
}
