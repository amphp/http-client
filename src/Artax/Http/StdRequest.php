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
    public function getStartLine() {
        if ('CONNECT' != $this->getMethod()) {
            $msg = $this->getMethod() . ' ';
            $msg.= ($this->uri->getPath() ?: '/');
            $msg.= ($queryStr = $this->uri->getQuery()) ? "?$queryStr" : '';
        } else {
            $msg = 'CONNECT ' . $this->uri->getAuthority();
        }
        
        // The leading space before "HTTP" matters! Don't delete it!
        $msg .= ' HTTP/' . $this->getHttpVersion();
        
        return $msg;
    }
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = $this->getStartLineAndHeaders();
        $msg.= $this->body ? $this->getBody() : '';
        
        return $msg;
    }
}
