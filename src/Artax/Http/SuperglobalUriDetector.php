<?php

namespace Artax\Http;

use RuntimeException;

class SuperglobalUriDetector {
    
    /**
     * Generates a StdUri from a superglobal $_SERVER array
     * 
     * @param array $_server
     * @return StdUri
     */
    public function make($_server) {
        
        if ($uri = $this->attemptProxyStyleParse($_server)) {
            return $uri;
        }
        
        $scheme = $this->detectScheme($_server);
        $host = $this->detectHost($_server);
        $path = $this->detectPath($_server);
        $query = $this->detectQuery($_server);
        
        $uri = "$scheme://$host" . $path;
        $uri.= $query ? "?$query" : '';
        
        return new StdUri($uri);
    }
    
    /**
     * @param array $_server
     * @return StdUri
     */
    private function attemptProxyStyleParse($_server) {
        // If the raw HTTP request message arrives with a proxy-style absolute URI in the
        // initial request line, the absolute URI is stored in $_SERVER['REQUEST_URI'] and
        // we need only parse that.
        if (isset($_server['REQUEST_URI'])
            && parse_url($_server['REQUEST_URI'], PHP_URL_SCHEME)
        ) {
            return new StdUri($_server['REQUEST_URI']);
        }
        
        return null;
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
     * @return string Returns "http" or "https" depending on the URI scheme
     */
    private function detectScheme(array $_server) {
        if (!isset($_server['HTTPS'])) {
            return 'http';
        }
        
        return filter_var($_server['HTTPS'], FILTER_VALIDATE_BOOLEAN) ? 'https' : 'http';
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectHost(array $_server) {
        if ($this->isIpBasedHost($_server)) {
            
            if (empty($_server['HTTP_HOST'])) {
                return $this->determineIpBasedHost($_server);
            }
            
            // Only trust Host header on IP-based vhost if it matches the server name
            if (preg_match(",^({$_server['SERVER_NAME']})(:\d+)?$,", $_server['HTTP_HOST'], $m)) {
                $portMatch = isset($m[2]) ? ltrim($m[2], ':') : null;
                if (!$portMatch && $m[1] == $_server['HTTP_HOST']) {
                    return $_server['HTTP_HOST'];
                } elseif ($portMatch == $this->detectPort($_server)) {
                    return $_server['HTTP_HOST'];
                }
            }
            
            return $this->determineIpBasedHost($_server);
            
        } elseif (!empty($_server['HTTP_HOST'])) {
            
            return $_server['HTTP_HOST'];
            
        } else {
            
            throw new RuntimeException(
                "Could not detect Host from superglobal"
            );
        }
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function determineIpBasedHost($_server) {
        $port = $this->detectPort($_server);
        $scheme = $this->detectScheme($_server);
        
        if (($port == 80 && $scheme == 'http') || ($port == 443 && $scheme == 'https')) {
            return $_server['SERVER_NAME'];
        } else {
            return $_server['SERVER_NAME'] . ":$port";
        }
    }
    
    /**
     * @param array $_server
     * @return bool
     */
    private function isIpBasedHost(array $_server) {
        if (empty($_server['SERVER_NAME'])) {
            throw new RuntimeException(
                "Cannot reliably detect URI Host from superglobal: missing SERVER_NAME key"
            );
        }
        
        return filter_var($_server['SERVER_NAME'], FILTER_VALIDATE_IP);
    }
    
    /**
     * @param array $_server
     * @return string
     * @throws RuntimeException
     */
    private function detectPath($_server) {
        if (isset($_server['REQUEST_URI'])) {
            $path = $_server['REQUEST_URI'];
        } elseif (isset($_server['REDIRECT_URL'])) {
            $path = $_server['REDIRECT_URL'];
        } else {
            throw new RuntimeException(
                "Cannot reliably detect URI Path from superglobal: missing REQUEST_URI/REDIRECT_URL"
            );
        }
        
        $queryStr = strpos($path, '?');        
        if ($queryStr !== false) {
          $path = substr($path, 0, $queryStr);
        }
        
        return $path;
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectQuery(array $_server) {
        return isset($_server['QUERY_STRING']) ? $_server['QUERY_STRING'] : '';
    }
    
    /**
     * @param array $_server
     * @return string
     */
    private function detectPort(array $_server) {
        if (isset($_server['SERVER_PORT'])) {
            return $_server['SERVER_PORT'];
        } else {
            throw new RuntimeException(
                "Cannot reliably detect URI Host from superglobal: missing SERVER_PORT"
            );
        }
    }
}
