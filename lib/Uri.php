<?php

namespace Amp\Artax;

class Uri {
    private $scheme = '';
    private $user = '';
    private $pass = '';
    private $host = '';
    private $port = '';
    private $path = '';
    private $query = '';
    private $fragment = '';
    private $queryParameters = array();
    private $isIpV4 = false;
    private $isIpV6 = false;

    public function __construct($uri) {
        $uri = (string) $uri;

        if (!$parts = $this->parse($uri)) {
            throw new \DomainException(
                'Invalid URI specified at ' . get_class($this) . '::__construct Argument 1'
            );
        }

        $this->uri = $uri;

        foreach ($parts as $key => $value) {
            $this->{$key} = $value;
        }

        // http://www.apps.ietf.org/rfc/rfc3986.html#sec-3.1
        // "schemes are case-insensitive"
        $this->scheme = strtolower($this->scheme);

        // http://www.apps.ietf.org/rfc/rfc3986.html#sec-3.2.2
        // "Although host is case-insensitive, producers and normalizers should use lowercase for
        // registered names and hexadecimal addresses for the sake of uniformity"
        $this->host = strtolower($this->host);

        if ($this->port && $this->scheme) {
            $this->normalizeDefaultPort();
        }

        $ip = @inet_pton($this->host);
        if (isset($ip[5])) {
            $this->isIpV6 = true;
        } elseif ($ip) {
            $this->isIpv4 = true;
        }

        $this->parseQueryParameters();

        if ($this->fragment) {
            $this->fragment = rawurldecode($this->fragment);
            $this->fragment = rawurlencode($this->fragment);
        }
    }

    private function parse($uri) {
        // PHP 5.4.7 fixed the incorrect parsing of network path references
        // @codeCoverageIgnoreStart
        if (PHP_VERSION_ID >= 50407) {
            return parse_url($uri);
        }
        // @codeCoverageIgnoreEnd

        $isPhp533 = PHP_VERSION_ID >= 50303;

        // PHP < 5.3.3 triggers E_WARNING on failure
        $parts = $isPhp533 ? parse_url($uri) : @parse_url($uri);

        // If no path is present or it's not a network path reference we're finished
        if (!isset($parts['path']) || substr($parts['path'], 0, 2) !== '//') {
            return $parts;
        }

        $schemeExists = isset($parts['scheme']);
        $tmpScheme = $schemeExists ? $parts['scheme'] : 'scheme';

        $tmpUri = $tmpScheme . ':' . $parts['path'];
        $tmpParts = $isPhp533 ? parse_url($tmpUri) : @parse_url($tmpUri);

        $parts['host'] = $tmpParts['host'];

        if (isset($tmpParts['path'])) {
            $parts['path'] = $tmpParts['path'];
        } else {
            unset($parts['path']);
        }

        return $parts;
    }

    public function __toString() {
        return $this->reconstitute(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * @link http://tools.ietf.org/html/rfc3986#section-5.3
     */
    private function reconstitute($scheme, $authority, $path, $query, $fragment) {
        $result = '';

        if ($scheme) {
            $result .= $scheme . ':';
        }

        if ($authority) {
            $result .= '//';
            $result .= $authority;
        }

        $result .= $path;

        if ($query) {
            $result .= '?';
            $result .= $query;
        }

        if ($fragment) {
            $result .= '#';
            $result .= $fragment;
        }

        return $result;
    }

    /**
     * Normalizes the URI for maximal comparison success
     *
     * @return string
     */
    public function normalize() {
        if (!$this->uri) {
            return '';
        }

        $path = $this->path ?: '/';
        $path = $this->removeDotSegments($path);
        $path = $this->decodeUnreservedCharacters($path);
        $path = $this->decodeReservedSubDelimiters($path);

        return $this->reconstitute(
            $this->scheme,
            $this->getAuthority(),
            $path,
            $this->query,
            $this->fragment
        );
    }

    /**
     * "URI producers and normalizers should omit the port component and its ":" delimiter if port
     * is empty or if its value would be the same as that of the scheme's default."
     *
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-3.2.3
     */
    private function normalizeDefaultPort() {
        switch($this->scheme) {
            case 'http':
                $this->port = ($this->port == 80) ? '' : $this->port;
                break;
            case 'https':
                $this->port = ($this->port == 443) ? '' : $this->port;
                break;
            case 'ftp':
                $this->port = ($this->port == 21) ? '' : $this->port;
                break;
            case 'ftps':
                $this->port = ($this->port == 990) ? '' : $this->port;
                break;
            case 'smtp':
                $this->port = ($this->port == 25) ? '' : $this->port;
                break;
        }
    }

    /**
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-5.2.4
     */
    private function removeDotSegments($input) {
        $output = '';

        $patternA  = ',^(\.\.?/),';
        $patternB1 = ',^(/\./),';
        $patternB2 = ',^(/\.)$,';
        $patternC  = ',^(/\.\./|/\.\.),';
        // $patternD  = ',^(\.\.?)$,';
        $patternE  = ',(/*[^/]*),';

        while ($input !== '') {
            if (preg_match($patternA, $input)) {
                $input = preg_replace($patternA, '', $input);
            } elseif (preg_match($patternB1, $input, $match) || preg_match($patternB2, $input, $match)) {
                $input = preg_replace(",^" . $match[1] . ",", '/', $input);
            } elseif (preg_match($patternC, $input, $match)) {
                $input = preg_replace(',^' . preg_quote($match[1], ',') . ',', '/', $input);
                $output = preg_replace(',/([^/]+)$,', '', $output);
            } elseif ($input == '.' || $input == '..') { // pattern D
                $input = '';
            } elseif (preg_match($patternE, $input, $match)) {
                $initialSegment = $match[1];
                $input = preg_replace(',^' . preg_quote($initialSegment, ',') . ',', '', $input, 1);
                $output .= $initialSegment;
            }
        }

        return $output;
    }

    /**
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-2.3
     */
    private function decodeUnreservedCharacters($str) {
        $str = rawurldecode($str);
        $str = rawurlencode($str);

        $encoded = array('%2F', '%3A', '%40');
        $decoded = array('/', ':', '@');

        return str_replace($encoded, $decoded, $str);
    }

    /**
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-2.2
     */
    private function decodeReservedSubDelimiters($str) {
        $encoded = array('%21', '%24', '%26', '%27', '%28', '%29', '%2A', '%2B', '%2C', '%3B', '%3D');
        $decoded = array('!', '$', '&', "'", '(', ')', '*', '+', ',', ';', '=');

        return str_replace($encoded, $decoded, $str);
    }

    /**
     * Is the specified URI string resolvable against the current URI instance?
     */
    public function canResolve($toResolve) {
        if (!(is_string($toResolve) || method_exists($toResolve, '__toString'))) {
            return false;
        }

        try {
            (new Uri($toResolve));
        } catch (\DomainException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $toResolve
     * @return Uri
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.2
     */
    public function resolve($toResolve) {
        $r = new Uri($toResolve);

        if ($r->__toString() === '') {
            return clone $this;
        }

        $base = $this;

        $t = new \StdClass;
        $t->scheme = '';
        $t->authority = '';
        $t->path = '';
        $t->query = '';
        $t->fragment = '';

        if ('' !== $r->getScheme()) {
            $t->scheme    = $r->getScheme();
            $t->authority = $r->getAuthority();
            $t->path      = $this->removeDotSegments($r->getPath());
            $t->query     = $r->getQuery();
        } else {
            if ('' !== $r->getAuthority()) {
                $t->authority = $r->getAuthority();
                $t->path      = $this->removeDotSegments($r->getPath());
                $t->query     = $r->getQuery();
            } else {
                if ('' == $r->getPath()) {
                    $t->path = $base->getPath();
                    if ($r->getQuery()) {
                        $t->query = $r->getQuery();
                    } else {
                        $t->query = $base->getQuery();
                    };
                } else {
                    if ($r->getPath() && substr($r->getPath(), 0, 1) == "/") {
                        $t->path = $this->removeDotSegments($r->getPath());
                    } else {
                        $t->path = $this->mergePaths($base->getPath(), $r->getPath());
                    };
                    $t->query = $r->getQuery();
                };
                $t->authority = $base->getAuthority();
            };
            $t->scheme = $base->getScheme();
        };

        $t->fragment = $r->getFragment();

        $result = $this->reconstitute($t->scheme, $t->authority, $t->path, $t->query, $t->fragment);

        return new Uri($result);
    }

    /**
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.3
     */
    private function mergePaths($basePath, $pathToMerge) {
        if ($basePath == '') {
            $merged = '/' . $pathToMerge;
        } else {
            $parts = explode('/', $basePath);
            array_pop($parts);
            $parts[] = $pathToMerge;
            $merged = implode('/', $parts);
        }

        return $this->removeDotSegments($merged);
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
    public function getUser() {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPass() {
        return $this->pass;
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
     * Retrieve the URI without the fragment component
     */
    public function getAbsoluteUri() {
        return $this->reconstitute(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $fragment = ''
        );
    }

    /**
     * @return bool
     */
    public function isIpV4() {
        return $this->isIpV4;
    }

    /**
     * @return bool
     */
    public function isIpV6() {
        return $this->isIpV6;
    }

    /**
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-3.2
     */
    public function getAuthority($hiddenPass = true) {
        $authority = $this->user;
        $authority.= $this->pass !== ''
            ? (':' . ($hiddenPass ? '********' : $this->pass))
            : '';
        $authority.= $authority ? '@' : '';

        if ($this->isIpV6) {
            $authority.= $this->port ? ("[{$this->host}]:{$this->port}") : "[{$this->host}]";
        } else {
            $authority.= $this->host;
            $authority.= $this->port ? (':' . $this->port) : '';
        }

        return $authority;
    }

    private function parseQueryParameters() {
        if ($this->query) {
            $parameters = [];
            foreach (explode("&", $this->query) as $pair) {
                $pair = explode("=", $pair, 2);
                $parameters[rawurldecode($pair[0])][] = rawurldecode(isset($pair[1]) ? $pair[1] : "");
            }

            $this->queryParameters = $parameters;
        }
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
     * @throws \DomainException
     */
    public function getQueryParameter($parameter) {
        if (!$this->hasQueryParameter($parameter)) {
            throw new \DomainException(
                "Invalid query parameter: `$parameter` does not exist"
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

    /**
     * @return array
     */
    public function getOriginalUri() {
        return $this->uri;
    }
}
