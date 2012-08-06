<?php

namespace Artax\Http;

use DomainException,
    RuntimeException,
    InvalidArgumentException;

class StdRequest extends StdMessage implements FormEncodableRequest {
    
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
    protected $queryParameters;
    
    /**
     * @var array
     */
    protected $bodyParameters = array();

    /**
     * @param mixed $uri A valid URI string or instance of Artax\Http\Uri
     * @param string $method
     * @param mixed $headers
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
        
        if ($body && $this->acceptsEntityBody()) {
            $this->body = $body;
        }
        
        $this->httpVersion = $httpVersion;
        
        $this->queryParameters = $this->parseParametersFromString($this->uri->getQuery());
        
        if ($body && $this->hasFormEncodedBody() && $this->acceptsEntityBody()) {
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
     * This method will read the entire contents of a stream body into memory to output as a string.
     * If a streamed body is preferred, manually output the raw message headers using
     * StdRequest::getMessageHeaderStr(), get the entity body stream with StdRequest::getBodyStream
     * and manually send the contents of the body resource stream.
     * 
     * Messages generated in accordance with RFC2616 section 5:
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
     * 
     * @return string
     */
    public function __toString() {
        if (strcmp('CONNECT', $this->getMethod())) {
            $msg = $this->getMessageHeaderStr();
            $msg.= $this->body ? $this->getBody() : '';
            return $msg;
        } else {
            return $this->buildConnectMessage();
        }
    }
    
    /**
     * @return string
     */
    public function getMessageHeaderStr() {
        $msg = $this->getMethod() . ' ' . $this->getPath();
        if ($queryStr = $this->getQuery()) {
            $msg.= "?$queryStr";
        }
        $msg.= ' HTTP/' . $this->getHttpVersion() . "\r\n";
        $msg.= 'HOST: ' . $this->getAuthority() . "\r\n";
        
        $headers = $this->getAllHeaders();
        unset($headers['HOST']);
        
        foreach ($headers as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        
        $msg.= "\r\n";
        
        return $msg;
    }
    
    /**
     * @return string
     */
    public function getProxyMessageHeaderStr() {
        $msg = $this->getMethod() . ' ' . $this->getUri() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion() . "\r\n";
        
        foreach ($this->getAllHeaders() as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        $msg.= "\r\n";
        
        return $msg;
    }
    
    /**
     * @return string
     */
    protected function buildConnectMessage() {
        $msg = 'CONNECT ' . $this->getAuthority() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion() . "\r\n";
        
        foreach ($this->getAllHeaders() as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        $msg.= "\r\n";
        
        return $msg;
    }
}
