<?php
/**
 * SuperglobalUriDetector Class File
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

/**
 * Generates a StdUri from a superglobal-type $_SERVER array
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class SuperglobalUriDetector {
    
    /**
     * Generates a StdUri from a superglobal $_SERVER array
     * 
     * @param array $_server
     * @return StdUri
     */
    public function make($_server) {
        $scheme = $this->detectScheme($_server);
        $host = $this->detectHost($_server);
        $port = $this->detectPort($_server);
        $path = $this->detectPath($_server);
        $query = $this->detectQuery($_server);
        
        return $this->normalizeProxyUri($scheme, $host, $port, $path, $query);
    }
    
    /**
     * The contents of the $_SERVER superglobal change if the raw HTTP message specifies a request
     * line with an absolute proxy-style URI as opposed to a standard HTTP/1.1 client request line
     * that specifies only the URI path/query component in conjunction with a Host header:
     * 
     * GET http://www.google.com HTTP/1.1
     * 
     * In this case, $_SERVER['HOST'] is not populated at all and the full absolute URI is stored in
     * $_SERVER['REQUEST_URI'].
     */
    private function normalizeProxyUri($scheme, $host, $port, $path, $query) {
        if (!$host && $parsed = parse_url($path)) {
            extract($parsed);
        }
        
        $uri = "$scheme://$host";
        $uri.= ($port != 80) ? ":$port" : '';
        $uri.= $path;
        $uri.= $query ? "?$query" : '';
        
        return new StdUri($uri);
    }
    
    /**
     * @param array $_server
     * @return string
     * @throws RuntimeException
     */
    private function detectPath($_server) {
        if (isset($_server['REQUEST_URI'])) {
            $uri = $_server['REQUEST_URI'];
        } elseif (isset($_server['REDIRECT_URL'])) {
            $uri = $_server['REDIRECT_URL'];
        } else {
            throw new RuntimeException("Could not detect URI path from superglobal");
        }
        
        $queryStr = strpos($uri, '?');        
        if ($queryStr !== false) {
          $uri = substr($uri, 0, $queryStr);
        }
        
        return $uri;
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectHost(array $_server) {
        return isset($_server['HTTP_HOST']) ? $_server['HTTP_HOST'] : '';
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
