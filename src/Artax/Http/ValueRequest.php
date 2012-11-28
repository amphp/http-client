<?php

namespace Artax\Http;

use Spl\DomainException,
    Artax\Uri;

/**
 * An immutable value object modeling HTTP Request messages
 */
class ValueRequest extends ValueMessage implements Request {
    
    /**
     * @var \Artax\Uri
     */
    private $uri;
    
    /**
     * @var string
     */
    private $method;
    
    /**
     * Note that request methods ARE case-sensitive as per RFC2616. Users should specify all-caps
     * strings for standard request method names like GET, POST, etc. These method names WILL NOT
     * be normalized as doing so would prevent the use of custom methods with lower-case characters.
     * Failure to correctly case standard method names may result in problems downstream. If you're
     * concerned about your ability to uppercase HTTP method verb strings, use the method verb
     * constants provided in the `Artax\Http\Request` interface.
     * 
     * @param string $method The HTTP method verb
     * @param string $uri The request URI string
     * @param string $protocol The HTTP protocol version (1.1, 1.0, 0.9)
     * @param mixed $headers An array, or Traversable or list of headers
     * @throws \Spl\DomainException On seriously malformed URIs
     */
    public function __construct($method, $uri, $protocol, $headers = null, $body = null) {
        $this->assignMethod($method);
        $this->assignUri($uri);
        $this->assignProtocol($protocol);
        
        if ($headers !== null) {
            $this->appendAllHeaders($headers);
        }
        
        if ($body !== null) {
            $this->assignBody($body);
        }
    }
    
    /**
     * @param string $uri
     * @throws \Spl\DomainException On seriously malformed URI
     */
    protected function assignUri($uri) {
        $this->uri = new Uri($uri);
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
     * @throws \Spl\DomainException On invalid method verb
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
     * Retrieve the request's HTTP method verb
     * 
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }
    
    /**
     * Build a raw HTTP message request line (without trailing CRLF)
     * 
     * @return string
     */
    public function getStartLine() {
        if (Request::CONNECT != $this->getMethod()) {
            $msg = $this->getMethod() . ' ';
            $msg.= ($this->uri->getPath() ?: '/');
            $msg.= ($queryStr = $this->uri->getQuery()) ? "?$queryStr" : '';
        } else {
            $msg = Request::CONNECT . ' ' . $this->uri->getAuthority();
        }
        
        // The leading space before the HTTP prefix matters! Don't delete it!
        $msg .= ' ' . Message::HTTP_PROTOCOL_PREFIX . $this->getProtocol();
        
        return $msg;
    }
    
    /**
     * Retrieve the request's associated URI
     * 
     * @return string
     */
    public function getUri() {
        return $this->uri->__toString();
    }
}
