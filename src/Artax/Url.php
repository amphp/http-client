<?php
/**
 * Url Class File
 * 
 * @category     Artax
 * @author       Levi Morrison <levim@php.net>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax;

/**
 * A URL representation
 *
 * @category     Artax
 * @author       Levi Morrison <levim@php.net>
 */
class Url implements Uri  {

    /**
     * @var string
     */
    private $scheme;
    
    /**
     * @var string
     */
    private $userInfo;
    
    /**
     * @var string
     */
    private $rawUserInfo;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $query;

    /**
     * @var string
     */
    private $fragment;
    
    /**
     * @param string $scheme
     * @param string $host
     * @param string $userInfo
     * @param int $port
     * @param string $path
     * @param string $query
     * @param string $fragment
     * 
     * @return void
     */
    public function __construct($scheme, $host, $userInfo = '', $port = 80, $path = '/', $query = '', $fragment = '') {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->path = '/' . ltrim($path, '/');
        $this->query = $query;
        $this->fragment = $fragment;
        
        $this->userInfo = $userInfo ? $this->protectUserInfo($userInfo) : '';
        $this->rawUserInfo = $userInfo;
    }
    
    /**
     * @param string $rawUserInfo
     * @return string
     */
    private function protectUserInfo($rawUserInfo) {
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
    public function getHost() {
        return $this->host;
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
     * @return int
     */
    public function getPort() {
        return $this->port;
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
        
        if ($this->port != 80) {
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
        
        if ($this->port != 80) {
            $authority .= ":{$this->port}";
        }
        
        return $authority;
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
