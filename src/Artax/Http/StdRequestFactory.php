<?php
/**
 * StdRequestFactory Class File
 * 
 * Because core PHP has no way to access the raw HTTP message, we cobble together the 
 * necessary StdRequest properties by parsing values from the superglobal $_SERVER array
 * and the `php://` input stream to instantiate new requests.
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
     * @param RequestDetector $requestDetector
     */
    public function __construct(RequestDetector $detector = null) {
        $this->requestDetector = $detector ?: new RequestDetector;
    }
    
    /**
     * @return Request
     * @throws DomainException
     */
    public function make(array $_server) {
        $url = $this->requestDetector->detectUrl($_server);
        $httpVersion = $this->requestDetector->detectHttpVersion($_server);
        $method = $this->requestDetector->detectMethod($_server);
        $headers = $this->requestDetector->detectHeaders($_server);
        $body = $this->requestDetector->detectBody();
        
        return new StdRequest($url, $httpVersion, $method, $headers, $body);
    }
}
