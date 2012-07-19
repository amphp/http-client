<?php
/**
 * SuperglobalToUrlTranslator Class File
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax;

/**
 * Generates a Url object from a superglobal-type $_SERVER array
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class SuperglobalToUrlTranslator {
    
    /**
     * Creates a Url instance from a superglobal $_SERVER array
     * 
     * Notice that there is no method for detecting URI fragments from the
     * end of the URI (#) because the server has no access to this data. For
     * more info on this subject, see the following:
     * 
     * http://www.w3.org/DesignIssues/Fragment.html
     * 
     * @param array $_server
     * @return Url Returns a Url instance
     */
    public function make(array $_server) {
        $host = $this->detectHost($_server);
        $path = $this->detectPath($_server);
        $port = $this->detectPort($_server);
        $query = $this->detectQuery($_server);
        $scheme = $this->detectScheme($_server);
        
        $url = new Url();
        $url->setScheme($scheme);
        $url->setHost($host);
        $url->setPort($port);
        $url->setPath($path);
        $url->setQuery($query);
        
        return $url;
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectPath($_server) {
        $uri = null;
        
        if (isset($_server['REQUEST_URI'])) {
            $uri = $_server['REQUEST_URI'];
        } elseif (isset($_server['REDIRECT_URL'])) {
            $uri = $_server['REDIRECT_URL'];
        }
        
        if ($uri) {
            $queryStr = strpos($uri, '?');        
            if ($queryStr !== false) {
              $uri = substr($uri, 0, $queryStr);
            }
        }
        
        return $uri;
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectHost(array $_server) {
        return isset($_server['HTTP_HOST']) ? $_server['HTTP_HOST'] : null;
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectPort(array $_server) {
        return isset($_server['REMOTE_PORT']) ? $_server['REMOTE_PORT'] : 80;
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectQuery(array $_server) {
        return isset($_server['QUERY_STRING']) ? $_server['QUERY_STRING'] : '';
    }
    
    /**
     * Determine URI scheme component from superglobal array
     * 
     * When using ISAPI with IIS, the value will be "off" if the request was
     * not made through the HTTPS protocol. As a result, we filter the
     * value to a bool.
     * 
     * @param array $_server A superglobal $_SERVER array
     * 
     * @return string Returns http or https depending on the URI scheme
     */
    private function detectScheme(array $_server) {
        if (isset($_server['HTTPS'])
            && filter_var($_server['HTTPS'], FILTER_VALIDATE_BOOLEAN)
        ) {
            return 'https';
        } else {
            return 'http';
        }
    }
}
