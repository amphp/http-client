<?php

namespace Artax;

use Spl\ValueException,
    Spl\DomainException;

class Uri {

    /**
     * @var string
     */
    private $scheme;

    /**
     * @var string
     */
    private $userInfo = '';

    /**
     * @var string
     */
    private $rawUserInfo = '';

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
    private $path = '/';

    /**
     * @var string
     */
    private $query = '';

    /**
     * @var string
     */
    private $fragment = '';

    /**
     * @var array
     */
    private $queryParameters;

    /**
     * @var bool
     */
    private $hasExplicitPort = false;

    /**
     * @var bool
     */
    private $hasExplicitTrailingHostSlash = false;

    /**
     * @param $uri
     * @throws \Spl\ValueException
     */
    public function __construct($uri) {
        $uri = (string) $uri;
        $this->parseUri($uri);
        $this->queryParameters = $this->parseQueryParameters($this->query);
    }

    /**
     * @param string $uri
     * @throws \Spl\ValueException
     * @return void
     */
    protected function parseUri($uri) {
        $r  = "(?:([a-z0-9+-._]+)://)?";
        $r .= "(?:";
        $r .=   "(?:((?:[a-z0-9-._~!$&'()*+,;=:]|%[0-9a-f]{2})*)@)?";
        $r .=   "(?:\[((?:[a-z0-9:])*)\])?";
        $r .=   "((?:[a-z0-9-._~!$&'()*+,;=]|%[0-9a-f]{2})*)";
        $r .=   "(?::(\d*))?";
        $r .=   "(/(?:[a-z0-9-._~!$&'()*+,;=:@/]|%[0-9a-f]{2})*)?";
        $r .=   "|";
        $r .=   "(/?";
        $r .=     "(?:[a-z0-9-._~!$&'()*+,;=:@]|%[0-9a-f]{2})+";
        $r .=     "(?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9a-f]{2})*";
        $r .=    ")?";
        $r .= ")";
        $r .= "(?:\?((?:[a-z0-9-._~!$&'()*+,;=:\/?@]|%[0-9a-f]{2})*))?";
        $r .= "(?:#((?:[a-z0-9-._~!$&'()*+,;=:\/?@]|%[0-9a-f]{2})*))?";

        preg_match("`$r`i", $uri, $match);

        $parts = array(
            'scheme'=>'',
            'userinfo'=>'',
            'host'=> '',
            'port'=>'',
            'path'=>'',
            'query'=>'',
            'fragment'=>''
       );

        switch (count($match)) {
            case 10: $parts['fragment'] = $match[9];
            case 9: $parts['query'] = $match[8];
            case 8: $parts['path'] = $match[7];
            case 7: $parts['path'] = $match[6] . $parts['path'];
            case 6: $parts['port'] = $match[5];
            case 5: $parts['host'] = $match[3] ? "[".$match[3]."]" : $match[4];
            case 4: $parts['userinfo'] = ($match[2]);
            case 3: $parts['scheme'] = $match[1];
        }

        if (empty($parts['scheme'])) {
            throw new ValueException(
                "Invalid URI; no scheme specified: `$uri`"
           );
        } elseif (empty($parts['host'])) {
            throw new ValueException(
                "Invalid URI; no host specified: `$uri`"
           );
        }
        
        $this->assignPropertiesFromParsedParts($parts);
    }
    
    /**
     * @param array $parts
     * @return void
     */
    protected function assignPropertiesFromParsedParts(array $parts) {
        // http://www.apps.ietf.org/rfc/rfc3986.html#sec-3.1
        // "schemes are case-insensitive"
        $this->scheme = strtolower($parts['scheme']);
        
        // http://www.apps.ietf.org/rfc/rfc3986.html#sec-3.2.2
        // "The host subcomponent is case-insensitive"
        $this->host = strtolower($parts['host']);
        
        $this->setUserInfo($parts['userinfo']);
        
        if (!empty($parts['port'])) {
            $this->port = (int) $parts['port'];
            $this->hasExplicitPort = true;
        } else {
            $this->port = $this->extrapolatePortFromScheme($this->scheme);
            $this->hasExplicitPort = false;
        }

        if (!$this->isZeroSafeEmpty($parts['path'])) {
            $this->path = $this->normalizePathDots($parts['path']);
            if ('/' == substr($parts['path'], -1)) {
                $this->hasExplicitTrailingHostSlash = true;
            }
        } else {
            $this->path = '/';
        }

        $this->query = $parts['query'];
        $this->fragment = $parts['fragment'];
    }
    
    protected function isZeroSafeEmpty($str) {
        return (empty($str) && $str !== '0');
    }
    
    /**
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-5.2.4
     */
    protected function normalizePathDots($input) {
        $output = '';
        
        $patternB1 = ',^(/\./),';
        $patternB2 = ',^(/\.)$,';
        $patternC  = ',^(/\.\./|/\.\.),';
        $patternE  = ',(/*[^/]*),';
        
        while (!$this->isZeroSafeEmpty($input)) {
            if (preg_match($patternB1, $input, $match) || preg_match($patternB2, $input, $match)) {
                $input = preg_replace(",^" . $match[1] . ",", '/', $input);
            } elseif (preg_match($patternC, $input, $match)) {
                $input = preg_replace(',^' . preg_quote($match[1], ',') . ',', '/', $input);
                $output = preg_replace(',/([^/]+)$,', '', $output);
            } elseif (preg_match($patternE, $input, $match)) {
                $initialSegment = $match[1];
                $input = preg_replace(',^' . preg_quote($initialSegment, ',') . ',', '', $input, 1);
                $output .= $initialSegment;
            }
        }

        return $output;
    }

    /**
     * @param string $scheme
     * @return int Returns default port for the specified scheme
     */
    protected function extrapolatePortFromScheme($scheme) {
        $scheme = strtolower($scheme);

        switch($scheme) {
            case 'http':
                $port = 80;
                break;
            case 'https':
                $port = 443;
                break;
            case 'ftp':
                $port = 21;
                break;
            case 'ftps':
                $port = 990;
                break;
            case 'smtp':
                $port = 25;
                break;
            default:
                $port = 0; // unsupported scheme
                break;
        }

        return $port;
    }

    /**
     * @param string $userInfo
     */
    protected function setUserInfo($userInfo) {
        $this->rawUserInfo = $userInfo;
        $this->userInfo = $userInfo ? $this->protectUserInfo($userInfo) : '';
    }

    /**
     * @param string $rawUserInfo
     * @return string
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
     * @param string $queryString
     * @return array
     */
    private function parseQueryParameters($queryString) {
        if ($queryString) {
            parse_str($queryString, $parameters);
            return array_map('urldecode', $parameters);
        } else {
            return array();
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
     *
     * @return string
     */
    public function getAuthority() {
        $authority = $this->userInfo ? "{$this->userInfo}@" : '';
        $authority.= $this->host;
        $authority.= $this->hasExplicitPort ? ":{$this->port}" : '';

        return $authority;
    }

    /**
     * Uses protected user info by default as per rfc3986-3.2.1
     *
     * @return string
     */
    public function __toString() {
        $uri = $this->scheme . '://' . $this->getAuthority();

        if ('/' == $this->path) {
            $uri .= $this->hasExplicitTrailingHostSlash ? '/' : '';
        } else {
            $uri .= $this->path;
        }

        if (!empty($this->query)) {
            $uri .= "?{$this->query}";
        }

        if (!empty($this->fragment)) {
            $uri .= "#{$this->fragment}";
        }

        return $uri;
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
     * @throws \Spl\DomainException
     */
    public function getQueryParameter($parameter) {
        if (!$this->hasQueryParameter($parameter)) {
            throw new DomainException(
                "The specified query parameter does not exist: $parameter"
           );
        }
        return $this->queryParameters[$parameter];
    }

    /**
     * @return array
     */
    public function getAllQueryParameters() {
        return $this->queryParameters;
    }
}