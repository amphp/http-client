<?php
/**
 * StdRequestFactory Class File
 * 
 * Because core PHP has no way to access the raw HTTP message, we cobble together the 
 * necessary StdRequest properties by parsing values from the superglobal $_SERVER array
 * and the `php://` input stream to populate a StdRequest value object.
 * 
 * @category     Artax
 * @package      Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Http;

use DomainException;

/**
 * Factory for creating new StdRequest value objects
 * 
 * @category     Artax
 * @package      Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class StdRequestFactory {
    
    /**
     * @var RequestDetector
     */
    private $requestDetector;
    
    /**
     * @param StdRequestDetector $requestDetector
     */
    public function __construct(StdRequestDetector $detector) {
        $this->requestDetector = $detector;
    }
    
    /**
     * @return Request
     * @throws DomainException
     */
    public function make(array $_server) {
        $uri = $this->requestDetector->detectUri($_server);
        $httpVersion = $this->requestDetector->detectHttpVersion($_server);
        $method = $this->requestDetector->detectMethod($_server);
        $headers = $this->requestDetector->detectHeaders($_server);
        $body = $this->requestDetector->detectBody();
        
        return new StdRequest($uri, $method, $headers, $body, $httpVersion);
    }
}
