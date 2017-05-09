<?php

namespace Amp\Artax\Cookie;

class ArrayCookieJar implements CookieJar {
    private $cookies = array();

    /**
     * Store a cookie
     *
     * @param Cookie $cookie
     * @return void
     */
    public function store(Cookie $cookie) {
        $this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
    }

    /**
     * Remove a specific cookie from storage
     *
     * @param Cookie $cookie
     * @return void
     */
    public function remove(Cookie $cookie) {
        unset($this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()]);
    }

    /**
     * Remove all stored cookies
     *
     * @return void
     */
    public function removeAll() {
        $this->cookies = array();
    }

    /**
     * Retrieve all stored cookies
     *
     * @return array Returns array in the format $arr[$domain][$path][$cookieName]
     */
    public function getAll() {
        return $this->cookies;
    }

    /**
     * Retrieve all cookies matching the specified constraints
     *
     * @param string $domain
     * @param string $path
     * @param string $name
     * @return array Returns an array (possibly empty) of all cookie matches
     */
    public function get($domain, $path = '', $name = null) {
        $this->clearExpiredCookies();

        $path = ($path === '') ? '/' : $path;
        $domain = strtolower($domain);

        $domainMatches = array();
        foreach (array_keys($this->cookies) as $cookieDomain) {
            if ($this->matchesDomain($domain, $cookieDomain)) {
                $domainMatches[] = $cookieDomain;
            }
        }

        $pathMatches = array();
        foreach ($domainMatches as $cookieDomain) {
            $paths = array_keys($this->cookies[$cookieDomain]);
            foreach ($paths as $cookiePath) {
                if ($this->matchesPath($path, $cookiePath)) {
                    $pathMatches[] = $this->cookies[$cookieDomain][$cookiePath];
                }
            }
        }


        $matches = array();
        foreach ($pathMatches as $nameArr) {
            foreach ($nameArr as $cookieName => $cookie) {
                if (!isset($name) || $name === $cookieName) {
                    $matches[] = $cookie;
                }
            }
        }

        return $matches;
    }

    private function clearExpiredCookies() {
        foreach ($this->cookies as $domain => $pathArr) {
            foreach ($pathArr as $path => $cookieArr) {
                foreach ($cookieArr as $name => $cookie) {
                    if ($cookie->isExpired()) {
                        unset($this->cookies[$domain][$path][$name]);
                    }
                }
            }
        }
    }

    /**
     * @link http://tools.ietf.org/html/rfc6265#section-5.1.3
     * @link http://erik.io/blog/2014/03/04/definitive-guide-to-cookie-domains/
     */
    private function matchesDomain($requestDomain, $cookieDomain) {
        if ($requestDomain === \ltrim($cookieDomain, ".")) {
            return true;
        }

        if (!isset($cookieDomain[0]) || $cookieDomain[0] !== '.') {
            return false;
        }

        if (\filter_var($requestDomain, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (\substr($requestDomain, 0, -\strlen($cookieDomain)) . $cookieDomain === $requestDomain) {
            return true;
        }

        return false;
    }

    /**
     * @link http://tools.ietf.org/html/rfc6265#section-5.1.4
     */
    private function matchesPath($requestPath, $cookiePath) {
        if ($requestPath === $cookiePath) {
            $isMatch = true;
        } elseif (strpos($requestPath, $cookiePath) === 0
            && (substr($cookiePath, -1) === '/' || $requestPath[strlen($cookiePath)] === '/')
        ) {
            $isMatch = true;
        } else {
            $isMatch = false;
        }

        return $isMatch;
    }
}
