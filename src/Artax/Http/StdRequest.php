<?php

namespace Artax\Http;

use DomainException,
    RuntimeException,
    InvalidArgumentException;

class StdRequest extends StdMessage implements Request {
    
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
        
        $this->httpVersion = $httpVersion ?: '1.1';
        
        $this->queryParameters = $this->parseParametersFromString($this->uri->getQuery());
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
     * @param string $httpMethod
     * @return bool
     */
    protected function acceptsEntityBody() {
        $methodsDisallowingEntityBody = array('GET', 'HEAD', 'DELETE', 'TRACE', 'CONNECT');
        return !in_array($this->getMethod(), $methodsDisallowingEntityBody);
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
     * Access the entity body
     * 
     * If a resource stream is assigned to the body property, its entire contents will be read into
     * memory and returned as a string. To access the stream resource directly without buffering
     * its contents, use Message::getBodyStream().
     * 
     * In web environments, php://input is a stream to the HTTP request body, but it can only be
     * read once and is not seekable. So we load it into our own stream if php://input is our 
     * request body property.
     * 
     * @return string
     */
    public function getBody() {
        if (!is_resource($this->body)) {
            return $this->body;
        }
        
        if (!is_null($this->cachedBodyFromStream)) {
            return $this->cachedBodyFromStream;
        }
        
        $meta = stream_get_meta_data($this->body);
        
        if ($meta['uri'] == 'php://input') {
            $this->cachedBodyFromStream = '';
            
            $tempStream = fopen('php://memory', 'r+');
            while (!feof($this->body)) {
                $data = fread($this->body, 8192);
                $this->cachedBodyFromStream .= $data;
                fwrite($tempStream, $data);
            }
            rewind($tempStream);
            fclose($this->body);
            $this->body = $tempStream;
            
        } else {
            $this->cachedBodyFromStream = stream_get_contents($this->body);
            rewind($this->body);
        }
        
        return $this->cachedBodyFromStream;
    }
    
    /**
     * Access the entity body resource stream directly without buffering its contents
     * 
     * @return resource
     */
    public function getBodyStream() {
        if (!is_resource($this->body)) {
            return null;
        }
        
        $meta = stream_get_meta_data($this->body);
        if ($meta['uri'] == 'php://input') {
            $tempStream = fopen('php://memory', 'r+');
            stream_copy_to_stream($this->body, $tempStream);
            $this->body = $tempStream;
        }
        
        return $this->body;
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
     * Build a raw HTTP message request line
     * 
     * @return string
     */
    public function getRequestLine() {
        $msg = $this->getMethod() . ' ' . $this->getPath();
        if ($queryStr = $this->getQuery()) {
            $msg.= "?$queryStr";
        }
        $msg.= ' HTTP/' . $this->getHttpVersion();
        
        return $msg;
    }
    
    /**
     * Build a raw HTTP message request line using the proxy-style absolute URI
     * 
     * @return string
     */
    public function getProxyRequestLine() {
        $msg = $this->getMethod() . ' ' . $this->getUri() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion();
        
        return $msg;
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
            $msg = $this->getRequestLine() . "\r\n";
            $msg.= 'HOST: ' . $this->getAuthority() . "\r\n";
            
            $headers = $this->getAllHeaders();
            unset($headers['HOST']);
            foreach ($headers as $header => $value) {
                $msg.= "$header: $value\r\n";
            }
            
            $msg.= "\r\n";
            $msg.= $this->body ? $this->getBody() : '';
            
            return $msg;
            
        } else {
            return $this->buildConnectMessage();
        }
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
