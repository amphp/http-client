<?php
/**
 * HTTP ParameterizedRequest Class File
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

use DomainException,
    RuntimeException,
    Artax\Uri;

/**
 * Adds parameterized request body accessor methods to the StdRequest
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ParameterizedRequest extends StdRequest {
    
    /**
     * @var array
     */
    private $bodyParameters;

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
        parent::__construct($uri, $httpVersion, $method, $headers, $body);
        
        if ($this->hasFormEncodedBody() && $this->acceptsEntityBody()) {
            $this->bodyParameters = $this->parseParametersFromString($this->getBody());
        } else {
            $this->bodyParameters = array();
        }
    }
    
    /**
     * @return bool
     */
    private function hasFormEncodedBody() {
        if (!$this->hasHeader('Content-Type')) {
            return false;
        }
        return strtolower($this->getHeader('Content-Type')) === 'application/x-www-form-urlencoded';
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
