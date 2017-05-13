<?php

namespace Amp\Artax\Cookie;

class ArrayCookieJar implements CookieJar {
    private $cookies = [];

    /**
     * Store a cookie.
     *
     * @param Cookie $cookie
     *
     * @return void
     */
    public function store(Cookie $cookie) {
        $this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
    }

    /**
     * Remove a specific cookie from the storage.
     *
     * @param Cookie $cookie
     */
    public function remove(Cookie $cookie) {
        unset($this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()]);
    }

    /**
     * Remove all stored cookies.
     */
    public function removeAll() {
        $this->cookies = [];
    }

    /**
     * Retrieve all stored cookies.
     *
     * @return array Returns array in the format `$array[$domain][$path][$cookieName]`.
     */
    public function getAll(): array {
        return $this->cookies;
    }

    /**
     * Retrieve all cookies matching the specified constraints.
     *
     * @param string $domain
     * @param string $path
     * @param string $name
     *
     * @return array Returns an array (possibly empty) of all cookie matches.
     */
    public function get(string $domain, string $path = '', string $name = null): array {
        $this->clearExpiredCookies();

        $path = $path === "" ? "/" : $path;
        $domain = \strtolower($domain);

        $matches = [];

        foreach ($this->cookies as $cookieDomain => $domainCookies) {
            if (!$this->matchesDomain($domain, $cookieDomain)) {
                continue;
            }

            foreach ($domainCookies as $cookiePath => $pathCookies) {
                if (!$this->matchesPath($path, $cookiePath)) {
                    continue;
                }

                foreach ($pathCookies as $cookieName => $cookie) {
                    if (!isset($name) || $name === $cookieName) {
                        $matches[] = $cookie;
                    }
                }
            }
        }

        return $matches;
    }

    private function clearExpiredCookies() {
        foreach ($this->cookies as $domain => $domainCookies) {
            foreach ($domainCookies as $path => $pathCookies) {
                foreach ($pathCookies as $name => $cookie) {
                    /** @var Cookie $cookie */
                    if ($cookie->isExpired()) {
                        unset($this->cookies[$domain][$path][$name]);
                    }
                }
            }
        }
    }

    /**
     * @param string $requestDomain
     * @param string $cookieDomain
     *
     * @return bool
     *
     * @link http://tools.ietf.org/html/rfc6265#section-5.1.3
     */
    private function matchesDomain(string $requestDomain, string $cookieDomain): bool {
        if ($requestDomain === \ltrim($cookieDomain, ".")) {
            return true;
        }

        if (!($isWildcardCookieDomain = ($cookieDomain[0] === '.'))) {
            return false;
        }

        if (filter_var($requestDomain, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (substr($requestDomain, 0, -\strlen($cookieDomain)) . $cookieDomain === $requestDomain) {
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
