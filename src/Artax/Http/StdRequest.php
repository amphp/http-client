<?php

namespace Artax\Http;

use Artax\Uri;

class StdRequest extends StdMessage implements Request {
    
    /**
     * @var \Artax\Uri
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
     * @var string
     */
    protected $cachedStreamBody;

    /**
     * Note that request methods ARE case-sensitive as per RFC2616. Users should specify all-caps
     * strings for standard request method names like GET, POST, etc.
     *
     * @param string $uri
     * @param string $method
     * @throws \Spl\ValueException
     */
    public function __construct($uri, $method = self::GET) {
        $this->uri = $uri instanceof Uri ? $uri : new Uri($uri);
        $this->method = $method;
        $this->queryParameters = $this->parseParametersFromString($this->uri->getQuery());
    }
    
    /**
     * @param string $paramString
     * @return array
     */
    protected function parseParametersFromString($paramString) {
        if ($paramString) {
            parse_str($paramString, $parameters);
            return array_map('urldecode', $parameters);
        } else {
            return array();
        }
    }
    
    /**
     * Retrieve the request's HTTP method verb
     * 
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }
    
    /**
     * Retrieve the HTTP message entity body in string form
     * 
     * If a resource stream is assigned to the body property, its entire contents will be read into
     * memory and returned as a string. To access the stream resource directly without buffering
     * its contents, use Message::getBodyStream().
     * 
     * In web environments, php://input is a stream to the HTTP request body, but it can only be
     * read once and is not seekable. As a result, we load it into our own stream if php://input
     * is our request body property.
     * 
     * @return string
     */
    public function getBody() {
        if (!is_resource($this->body)) {
            return (string) $this->body;
        }
        
        if (!empty($this->cachedStreamBody)) {
            return $this->cachedStreamBody;
        }
        
        $meta = stream_get_meta_data($this->body);
        
        if ($meta['uri'] == 'php://input') {
            $this->cachedStreamBody = '';
            
            $tempStream = fopen('php://memory', 'r+');
            while (!feof($this->body)) {
                $data = fread($this->body, 8192);
                $this->cachedStreamBody .= $data;
                fwrite($tempStream, $data);
            }
            rewind($tempStream);
            fclose($this->body);
            $this->body = $tempStream;
            
        } else {
            rewind($this->body);
            $this->cachedStreamBody = stream_get_contents($this->body);
            rewind($this->body);
        }
        
        return $this->cachedStreamBody;
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
            rewind($tempStream);
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
    public function getUserInfo() {
        return $this->uri->getUserInfo();
    }
    
    /**
     * @param string $parameter
     * @return bool
     */
    public function hasQueryParameter($parameter) {
        return $this->uri->hasQueryParameter($parameter);
    }
    
    /**
     * @param string $parameter
     * @return string
     * @throws \Spl\DomainException
     */
    public function getQueryParameter($parameter) {
        return $this->uri->getQueryParameter($parameter);
    }
    
    /**
     * @return array
     */
    public function getAllQueryParameters() {
        return $this->uri->getAllQueryParameters();
    }
    
    /**
     * Build a raw HTTP message request line (without trailing CRLF)
     * 
     * @return string
     */
    public function getRequestLine() {
        if ('CONNECT' != $this->getMethod()) {
            $msg = $this->getMethod() . ' ' . $this->getPath();
            $msg.= ($queryStr = $this->getQuery()) ? "?$queryStr" : '';
        } else {
            $msg = 'CONNECT ' . $this->getAuthority();
        }
        
        // The leading space before "HTTP" matters! Don't delete it!
        $msg .= ' HTTP/' . $this->getHttpVersion();
        
        return $msg;
    }
    
    /**
     * Build a raw HTTP request line using the proxy-style absolute URI
     * 
     * @return string
     */
    public function getProxyRequestLine() {
        $msg = $this->getMethod() . ' ' . $this->getUri() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion();
        
        return $msg;
    }
    
    /**
     * Get the raw HTTP message contents up to and including the terminating header CRLFs
     * 
     * @return string
     */
    public function getRawRequestLineAndHeaders() {
        $msg = $this->getRequestLine() . "\r\n";
        $msg.= $this->getRawHeaders();
        $msg.= "\r\n";
        
        return $msg;
    }
    
    /**
     * Returns a fully stringified HTTP request message
     * 
     * @return string
     */
    public function __toString() {
        $msg = $this->getRawRequestLineAndHeaders();
        $msg.= $this->body ? $this->getBody() : '';
        
        return $msg;
    }
}
