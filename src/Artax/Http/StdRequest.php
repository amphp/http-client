<?php

namespace Artax\Http;

use Ardent\TypeException,
    Ardent\DomainException,
    Artax\Uri;

/**
 * A mutable object used to incrementally build HTTP Requests
 */
class StdRequest extends StdMessage implements MutableRequest {
    
    /**
     * @var \Artax\Uri
     */
    private $uri;
    
    /**
     * @var string
     */
    private $method;
    
    /**
     * Retrieve the request URI
     * 
     * @return string
     */
    public function getUri() {
        return $this->uri ? $this->uri->__toString() : null;
    }
    
    /**
     * Assign the request URI
     * 
     * @param string $uri
     * @throws \Ardent\DomainException On some seriously malformed URIs
     * @return void
     */
    public function setUri($uri) {
        $this->uri = new Uri($uri);
    }
    
    /**
     * Retrieve the HTTP method verb
     * 
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }
    
    /**
     * Assign the HTTP method verb
     * 
     * @param string $method
     * @throws \Ardent\DomainException On invalid method
     * @return void
     */
    public function setMethod($method) {
        $this->assignMethod($method);
    }
    
    /**
     * Assign a request method
     * 
     * token          = 1*<any CHAR except CTLs or separators>
     * separators     = "(" | ")" | "<" | ">" | "@"
     *                | "," | ";" | ":" | "\" | <">
     *                | "/" | "[" | "]" | "?" | "="
     *                | "{" | "}" | SP | HT
     * 
     * @param string $method
     * @throws \Ardent\DomainException On invalid method verb
     * @return void
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5.1.1
     */
    protected function assignMethod($method) {
        $pattern = ",^\s*([^\x{00}-\x{20}\(\)<>@\,;:\"/\[\]\?={}\\\\]+)\s*$,";
        
        if (preg_match($pattern, $method, $match)) {
            $this->method = $match[1];
        } else {
            throw new DomainException(
                "Invalid method verb: method may not contain CTL or separator characters"
            );
        }
    }
    
    /**
     * Build a raw HTTP message request line (without trailing CRLF)
     * 
     * @throws \Ardent\DomainException On missing HTTP version or method verb
     * @return string
     */
    public function getStartLine() {
        if (!($this->getProtocol() && $this->getMethod() && $this->uri)) {
            throw new DomainException(
                'Cannot generate request start-line: method verb, URI and HTTP version required'
            );
        } elseif (Request::CONNECT !== $this->getMethod()) {
            $msg = $this->getMethod() . ' ';
            $msg.= ($this->uri->getPath() ?: '/');
            $msg.= ($queryStr = $this->uri->getQuery()) ? "?$queryStr" : '';
        } else {
            $msg = Request::CONNECT . ' ' . $this->uri->getAuthority();
        }
        
        // The leading space before "HTTP" matters! Don't delete it!
        $msg .= ' ' . Message::HTTP_PROTOCOL_PREFIX . $this->getProtocol();
        
        return $msg;
    }
    
    /**
     * Import all properties of an existing Request implementation into the current instance
     * 
     * @param Request $request
     * @throws \Ardent\TypeException If non-Request argument specified
     * @return void
     */
    public function import($request) {
        if (!$request instanceof Request) {
            throw new TypeException(
                get_class($this) . '::import() requires an instance of Artax\\Http\\Request at ' .
                'Argument 1'
            );
        }
        
        $this->setUri($request->getUri());
        $this->setProtocol($request->getProtocol());
        $this->setMethod($request->getMethod());
        $this->setAllHeaders($request->getAllHeaders());
        $this->assignBody($request->getBody());
    }
    
    /**
     * Export an immutable ValueRequest from the current instance
     * 
     * @throws \Ardent\DomainException On missing method/URI/protocol
     * @return ValueRequest
     */
    public function export() {
        if (!($this->getProtocol() && $this->getUri() && $this->getMethod())) {
            throw new DomainException(
                "Protocol, method and URI must be assigned prior to exporting a request"
            );
        }
        
        return new ValueRequest(
            $this->getMethod(),
            $this->getUri(),
            $this->getProtocol(),
            $this->getAllHeaders(),
            $this->getBody()
        );
    }
}
