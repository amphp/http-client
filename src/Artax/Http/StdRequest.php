<?php

namespace Artax\Http;

use Artax\Uri;

class StdRequest extends StdMessage implements Request {
    
    /**
     * @var \Artax\Uri
     */
    private $uri;
    
    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $cachedStreamBody;
    
    /**
     * @var int
     */
    private $streamReadSizeInBytes = 8192;
    
    /**
     * Note that request methods ARE case-sensitive as per RFC2616. Users should specify all-caps
     * strings for standard request method names like GET, POST, etc. These method names WILL NOT
     * be normalized as doing so would prevent the use of custom methods with lower-case characters.
     * Failure to correctly case standard method names may result in problems downstream. If you're
     * concerned about your ability to uppercase HTTP method verb strings, use the method verb
     * constants provided in the `Artax\Http\Request` interface.
     *
     * @param string $uri The request URI string
     * @param string $method The HTTP method verb
     * @throws \Spl\ValueException On seriously malformed URIs
     */
    public function __construct($uri, $method = Request::GET) {
        $this->uri = new Uri($uri);
        $this->method = $method;
    }
    
    /**
     * Retrieve the request's associated URI
     * 
     * @return string
     */
    public function getUri() {
        return $this->uri->__toString();
    }
    
    /**
     * Retrieve the request's HTTP method verb
     * 
     * @return string
     */
    final public function getMethod() {
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
            
            $tempStream = fopen('php://temp', 'r+');
            while (!feof($this->body)) {
                $data = fread($this->body, $this->streamReadSizeInBytes);
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
     * Access the entity body resource stream directly without buffering its full contents
     * 
     * @return resource Returns the stream entity body or NULL if the entity body is not a stream
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
     * Does the request URI expose the specified query parameter?
     * 
     * @param string $parameter
     * @return bool
     */
    public function hasQueryParameter($parameter) {
        return $this->uri->hasQueryParameter($parameter);
    }
    
    /**
     * Retrieve the value of the specified URI query parameter
     * 
     * @param string $parameter The name of a query parameter from the request URI
     * @return string The query parameter value
     * @throws \Spl\DomainException If the specified parameter does not exist
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
     * An alias for StdRequest::getStartLine
     * 
     * @return string
     * @see StdRequest::getStartLine
     */
    public function getRequestLine() {
        return $this->getStartLine();
    }
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = $this->getStartLineAndHeaders();
        $msg.= $this->body ? $this->getBody() : '';
        
        return $msg;
    }
    
    /**
     * @return \Artax\Uri
     */
    final protected function getUriInstance() {
        return $this->uri;
    }
    
    /**
     * @return string Returns NULL if stream entity body contents have not been buffered
     */
    final protected function getCachedStreamBody() {
        return $this->cachedStreamBody;
    }
}
