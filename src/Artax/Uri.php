<?php

namespace Artax;

use Spl\TypeException,
    Spl\ValueException;

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
    private $port = 80;

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
     * @var bool
     */
    private $explicitPortSpecified = false;

    /**
     * @var bool
     */
    private $explicitTrailingHostSlash = false;

    /**
     * @param string $uri
     * @throws \Spl\ValueException
     * @return void
     */
    public function __construct($uri) {
        $uri = (string) $uri;
        $this->parseUri($uri);
    }

    /**
     * @param string $uri
     * @throws \Spl\ValueException
     */
    protected function parseUri($uri) {
        $uriParts = @parse_url($uri);

        if (empty($uriParts['scheme'])) {
            throw new ValueException(
                "Invalid URI string: $uri"
            );
        }

        $this->scheme = $uriParts['scheme'];
        $this->host = $uriParts['host'];

        if (isset($uriParts['port'])) {
            $this->port = (int) $uriParts['port'];
            $this->explicitPortSpecified = true;
        } else {
            $this->port = strcmp('https', $uriParts['scheme']) ? 80 : 443;
            $this->explicitPortSpecified = false;
        }

        if (isset($uriParts['path'])) {
            $this->path = $uriParts['path'];
            if ('/' == $uriParts['path']) {
                $this->explicitTrailingHostSlash = true;
            }
        } else {
            $this->path = '/';
        }

        $this->query = isset($uriParts['query']) ? $uriParts['query'] : '';
        $this->fragment = isset($uriParts['fragment']) ? $uriParts['fragment'] : '';

        $userInfo = '';
        if (!empty($uriParts['user'])) {
            $userInfo .= $uriParts['user'];
        }
        if ($userInfo && !empty($uriParts['pass'])) {
            $userInfo .= ':' . $uriParts['pass'];
        }

        $this->setUserInfo($userInfo);
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
     *
     * @return string
     */
    public function getAuthority() {
        $authority = $this->userInfo ? $this->userInfo.'@' : '';
        $authority .= $this->host;

        if ($this->explicitPortSpecified) {
            $authority .= ":{$this->port}";
        }

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
            $uri .= $this->explicitTrailingHostSlash ? '/' : '';
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
     * @return bool
     */
    public function wasExplicitPortSpecified() {
        return $this->explicitPortSpecified;
    }
}