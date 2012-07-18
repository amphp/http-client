<?php
/**
 * HTTP StdRequest Class File
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Http;

use DomainException,
    RuntimeException,
    Artax\Uri;

/**
 * An immutable standard HTTP request model
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class StdRequest implements Request {
    
    /**
     * @var Uri
     */
    private $uri;
    
    /**
     * @var string
     */
    private $httpVersion;
    
    /**
     * @var string
     */
    private $method;
    
    /**
     * @var array
     */
    private $headers;
    
    /**
     * @var string
     */
    private $body;
    
    /**
     * @var string
     */
    private $decodedBody;
    
    /**
     * @var array
     */
    private $queryParameters;

    /**
     * @param Uri $uri
     * @param string $httpVersion
     * @param string $method
     * @param array $headers
     * @param string $body
     * @return void
     * @throws DomainException On invalid HTTP method verb
     */
    public function __construct(Uri $uri, $httpVersion, $method, array $headers, $body = '') {
        $this->uri = $uri;
        $this->headers = $this->normalizeHeaders($headers);
        $this->httpVersion = $httpVersion;
        $this->method = $this->normalizeMethod($method);
        $this->body = $this->acceptsEntityBody() ? $body : '';
        $this->queryParameters = $this->parseParametersFromString($uri->getQuery());
    }
    
    /**
     * @param array $headers
     * @return array
     */
    private function normalizeHeaders(array $headers) {
        return array_combine(array_map('strtoupper', array_keys($headers)), $headers);
    }
    
    /**
     * @param string $httpMethodVerb
     * @return string
     * @throws DomainException
     */
    private function normalizeMethod($httpMethodVerb) {
        $normalized = strtoupper($httpMethodVerb);
        $valid = array('OPTIONS', 'GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'CONNECT');
        if (!in_array($normalized, $valid)) {
            throw new DomainException(
                "Invalid HTTP method verb: $httpMethodVerb. Valid value domain: [" .
                implode('|', $valid) . ']'
            );
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
     * @param string $httpMethod
     * @return bool
     */
    protected function acceptsEntityBody() {
        return in_array($this->getMethod(), array('POST', 'PUT', 'OPTIONS'));
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
        return isset($this->headers[$upHeader]) || array_key_exists($upHeader, $this->headers);
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
}
