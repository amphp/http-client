<?php
/**
 * StdRequestDetector Class File
 * 
 * @category     Artax
 * @package      Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Http;

/**
 * Detects HTTP Request properties from an array of server values (superglobal)
 * 
 * @category     Artax
 * @package      Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class StdRequestDetector {
    
    /**
     * @SuperglobalUriDetector
     */
    private $uriDetector;
    
    /**
     * @param SuperglobalUriDetector $uriDetector
     * @return void
     */
    public function __construct(SuperglobalUriDetector $uriDetector) {
        $this->uriDetector = $uriDetector;
    }
    
    /**
     * @param array $_server
     * @return Url
     */
    public function detectUri($_server) {
        return $this->uriDetector->make($_server);
    }
    
    /**
     * @param array $_server
     * @return array
     */
    public function detectHeaders($_server) {
        if ($headers = $this->detectHeadersNatively()) {
            return $headers;
        }
        
        $includeWithoutHttpPrefix = array(
            'CONTENT-TYPE',
            'CONTENT-LENGTH',
            'CONTENT-MD5',
            'CONTENT-ENCODING',
            'CONTENT-RANGE',
            'CONTENT-LANGUAGE'
        );
        
        foreach ($_server as $name => $value) {
            $name = str_replace('_', '-', $name);
            if (0 === strpos($name, 'HTTP-')) {
                $name = substr($name, 5);
                $headers[$name] = $value; 
            } elseif (in_array($name, $includeWithoutHttpPrefix)) { 
                $headers[$name] = $value; 
            }
        }
        
        return $headers;
    }
    
    /**
     * @return array
     */
    protected function detectHeadersNatively() {
        return function_exists('getallheaders') ? getallheaders() : array();
    }
    
    /**
     * @param array $_server
     * @return string
     */
    public function detectHttpVersion($_server) {
        return preg_replace('#HTTP/#i', '', $_server['SERVER_PROTOCOL']);
    }
    
    /**
     * @param array $_server
     * @return string
     */
    public function detectMethod($_server) {
        return $_server['REQUEST_METHOD'];
    }
    
    /**
     * @return string
     */
    public function detectBody() {
        return file_get_contents('php://input');
    }
}
