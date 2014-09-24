<?php

namespace Amp\Artax\Cookie;

class Cookie {
    private $name;
    private $value;
    private $expires;
    private $path;
    private $domain;
    private $secure;
    private $httpOnly;

    private static $dateFormats = array(
        'D, d M Y H:i:s T',
        'D, d-M-y H:i:s T',
        'D, d-M-Y H:i:s T',
        'D, d-m-y H:i:s T',
        'D, d-m-Y H:i:s T',
        'D M j G:i:s Y',
        'D M d H:i:s Y T'
    );

    public function __construct($name, $value, $expires = null, $path = null, $domain = '', $secure = false, $httpOnly = true) {
        $this->name = $name;
        $this->value = $value;
        $this->expires = ($expires === null) ? null : (int) $expires;
        $this->path = $path ?: '/';
        $this->domain = strtolower($domain);
        $this->secure = (bool) $secure;
        $this->httpOnly = (bool) $httpOnly;
    }

    public function getName() {
        return $this->name;
    }

    public function getValue() {
        return $this->value;
    }

    public function getExpirationTime() {
        return $this->expires;
    }

    public function isExpired() {
        return $this->expires && $this->expires < time();
    }

    public function getPath() {
        return $this->path;
    }

    public function getDomain() {
        return $this->domain;
    }

    public function getSecure() {
        return $this->secure;
    }

    public function getHttpOnly() {
        return $this->httpOnly;
    }

    public function __toString() {
        $cookieStr = $this->name . '=' . $this->value;

        if ($this->expires !== null) {
            $expiryDate = \DateTime::createFromFormat('U', $this->expires, new \DateTimeZone('GMT'));
            $cookieStr .= '; expires=' . $expiryDate->format(self::$dateFormats[0]);
        }

        if ($this->domain !== '') {
            $cookieStr .= '; domain=' . $this->domain;
        }

        if ($this->path) {
            $cookieStr .= '; path=' . $this->path;
        }

        if ($this->secure) {
            $cookieStr .= '; secure';
        }

        if ($this->httpOnly) {
            $cookieStr .= '; httpOnly';
        }

        return $cookieStr;
    }

    static function fromString($rawCookieStr) {
        if (!$rawCookieStr) {
            throw new \InvalidArgumentException(
                'Invalid cookie string'
            );
        }

        $parts = explode(';', trim($rawCookieStr));
        $nvPair = array_shift($parts);


        if (strpos($nvPair, '=') === false) {
            throw new \InvalidArgumentException;
        }

        list($name, $value) = explode('=', $nvPair, 2);

        $attrStruct = array(
            'expires' => '',
            'path' => '',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'max-age' => null
        );

        foreach ($parts as $part) {
            $part = trim($part);
            if (0 === stripos($part, 'secure')) {
                $attrStruct['secure'] = true;
                continue;
            } elseif (0 === stripos($part, 'httponly')) {
                $attrStruct['httponly'] = true;
                continue;
            } elseif (strpos($part, '=') === false) {
                throw new \InvalidArgumentException(
                    'Invalid cookie string: ' . $part
                );
            }

            list($attr, $attrValue) = explode('=', $part, 2);

            $attr = strtolower($attr);
            if (array_key_exists($attr, $attrStruct)) {
                $attrStruct[$attr] = trim($attrValue, "\"\t\n\r\0\x0B\x20");
            }
        }

        $attrStruct['httponly'] = $attrStruct['httponly'] ? true : false;
        $attrStruct['secure'] = $attrStruct['secure'] ? true : false;

        if (isset($attrStruct['max-age']) && intval($attrStruct['max-age']) == $attrStruct['max-age']) {
            $attrStruct['expires'] = time() + $attrStruct['max-age'];
        } elseif ($attrStruct['expires']) {
            $attrStruct['expires'] = self::parseDate($attrStruct['expires']);
        }

        return new Cookie(
            $name,
            $value,
            $attrStruct['expires'],
            $attrStruct['path'],
            $attrStruct['domain'],
            $attrStruct['secure'],
            $attrStruct['httponly']
        );
    }

    private static function parseDate($dateStr) {
        foreach (self::$dateFormats as $dateFormat) {
            if ($date = \DateTime::createFromFormat($dateFormat, $dateStr, new \DateTimeZone('GMT'))) {
                return $date->getTimestamp();
            }
        }

        throw new \InvalidArgumentException(
            'Invalid expires attribute: ' . $dateStr
        );
    }
}
