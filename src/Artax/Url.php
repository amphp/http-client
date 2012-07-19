<?php
/**
 * Url Class File
 * 
 * @category    Artax
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax;

use InvalidArgumentException;

/**
 * An implementation of the Uri interface
 *
 * @category    Artax
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class Url implements Uri  {

    private $scheme = 'http';
    private $userInfo = '';
    private $rawUserInfo = '';
    private $host;
    private $port = 80;
    private $explicitPort = false;
    private $path = '/';
    private $query = '';
    private $fragment = '';
    
    public function __construct($fullUrlString) {
        $this->parseFullUrlString($fullUrlString);
    }
    
    protected function parseFullUrlString($fullUrlString) {
        if (!$urlParts = parse_url($fullUrlString)) {
            throw new InvalidArgumentException("Invalid URL: $fullUrlString");
        }
        
        if (!isset($urlParts['scheme'])) {
            throw new InvalidArgumentException(
                'Invalid URL: No scheme (http|https) specified'
            );
        }
        
        $this->scheme = $urlParts['scheme'];
        $this->host = $urlParts['host'];
        
        $explicitPort = isset($urlParts['port']);
        $this->port = $explicitPort ? $urlParts['port'] : 80;
        $this->explicitPort = $explicitPort;
        
        $this->path = isset($urlParts['path']) ? $urlParts['path'] : '/';
        $this->query = isset($urlParts['query']) ? $urlParts['query'] : '';
        $this->fragment = isset($urlParts['fragment']) ? $urlParts['fragment'] : '';
        
        $userInfo = '';
        if (!empty($urlParts['user'])) {
            $userInfo .= $urlParts['user'];
        }
        if ($userInfo && !empty($urlParts['pass'])) {
            $userInfo .= ':' . $urlParts['pass'];
        }
        
        $this->setUserInfo($userInfo);
        
    }
    
    /**
     * @param string $userInfo
     */
    protected function setUserInfo($userInfo) {
        $this->userInfo = $userInfo ? $this->protectUserInfo($userInfo) : '';
        $this->rawUserInfo = $userInfo;
    }
    
    /**
     * @param string $rawUserInfo
     */
    protected function protectUserInfo($rawUserInfo) {
        $colonPos = strpos($rawUserInfo, ':');
        
        // rfc3986-3.2.1 | http://tools.ietf.org/html/rfc3986#section-3.2
        // "Applications should not render as clear text any data
        // after the first colon (":") character found within a userinfo
        // subcomponent unless the data after the colon is the empty string
        // (indicating no password)"
        if ($colonPos !== FALSE && strlen($rawUserInfo)-1 > $colonPos) {
            return substr($rawUserInfo, 0, $colonPos) . ':********';
        } else {
            return $rawUserInfo;
        }
    }
    
    /**
     * @return string
     */
    public function getScheme() {
        return $this->scheme;
    }
    
    /**
     * @return string
     */
    public function getUserInfo() {
        return $this->userInfo;
    }
    
    /**
     * @return string
     */
    public function getRawUserInfo() {
        return $this->rawUserInfo;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getQuery() {
        return $this->query;
    }
    
    /**
     * @return string
     */
    public function getFragment() {
        return $this->fragment;
    }

    /**
     * Uses protected user info by default as per rfc3986-3.2.1
     * Url::getRawAuthority() is available if plain-text password information is desirable.
     * 
     * @return string
     */
    public function getAuthority() {
        $authority = $this->userInfo ? $this->userInfo.'@' : '';
        $authority .= $this->host;
        
        if ($this->explicitPort) {
            $authority .= ":{$this->port}";
        }
        
        return $authority;
    }
    
    /**
     * @return string
     */
    public function getRawAuthority() {
        $authority = $this->rawUserInfo ? $this->rawUserInfo.'@' : '';
        $authority .= $this->host;
        
        if ($this->explicitPort) {
            $authority .= ":{$this->port}";
        }
        
        return $authority;
    }
    
    /**
     * @return string
     */
    public function getRawUrl() {
        $url = $this->scheme . '://' . $this->getRawAuthority() . $this->path;
        
        if (!empty($this->query)) {
            $url .= "?{$this->query}";
        }

        if (!empty($this->fragment)) {
            $url .= "#{$this->fragment}";
        }

        return $url;
    }
    
    /**
     * Uses protected user info by default as per rfc3986-3.2.1
     * Url::getRawUrl() is available if plain-text password information is desirable.
     * 
     * @return string
     */
    public function __toString() {
        $url = $this->scheme . '://' . $this->getAuthority() . $this->path;

        if (!empty($this->query)) {
            $url .= "?{$this->query}";
        }

        if (!empty($this->fragment)) {
            $url .= "#{$this->fragment}";
        }

        return $url;
    }
}
