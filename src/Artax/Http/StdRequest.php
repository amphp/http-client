<?php
/**
 * HTTP StdRequest Class File
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

use DomainException,
    RuntimeException,
    InvalidArgumentException,
    Artax\Uri,
    Artax\Url;

/**
 * An immutable standard HTTP request model
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class StdRequest implements FormEncodableRequest {
    
    /**
     * @var Uri
     */
    private $uri;
    
    /**
     * @var string
     */
    private $method;
    
    /**
     * @var array
     */
    private $headers = array();
    
    /**
     * @var string
     */
    private $body;
    
    /**
     * @var string
     */
    private $httpVersion;
    
    /**
     * @var array
     */
    private $queryParameters;
    
    /**
     * @var array
     */
    private $bodyParameters = array();

    /**
     * @param mixed $uri A valid URI string or Artax\Uri instance
     * @param string $method
     * @param array $headers
     * @param string $body
     * @param string $httpVersion
     * @return void
     * @throws DomainException On invalid HTTP method verb
     */
    public function __construct($uri, $method, $headers = array(), $body = '', $httpVersion = '1.1') {
        $this->uri = $uri instanceof Uri ? $uri : $this->buildUri($uri);
        $this->httpVersion = $httpVersion;
        $this->method = strtoupper($method);
        $this->body = $this->acceptsEntityBody() ? $body : '';
        
        if ($headers) {
            $this->headers = $this->normalizeHeaders($headers);
        }
        
        $this->queryParameters = $this->parseParametersFromString($this->uri->getQuery());
        
        if ($this->hasFormEncodedBody() && $this->acceptsEntityBody()) {
            $this->bodyParameters = $this->parseParametersFromString($this->getBody());
        } else {
            $this->bodyParameters = array();
        }
    }
    
    /**
     * @param string $uri
     */
    private function buildUri($uri) {
        try {
            return new Url($uri);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                'Invalid URI specified at Argument 1 in ' . get_class($this) . '::__construct: ' .
                "$uri. Please specify a valid URI string or Artax\\Uri instance.", null, $e
            );
        }
    }
    
    /**
     * @param array $headers
     * @return array
     */
    private function normalizeHeaders(array $headers) {
        return array_combine(array_map('strtoupper', array_keys($headers)), $headers);
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
        }
        return strtolower($this->getHeader('Content-Type')) === 'application/x-www-form-urlencoded';
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
    public function getRawUri() {
        return $this->uri->getRawUrl();
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
}
