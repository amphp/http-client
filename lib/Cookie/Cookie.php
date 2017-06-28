<?php

namespace Amp\Artax\Cookie;

final class Cookie {
    private $name;
    private $value;
    private $expires;
    private $path;
    private $domain;
    private $secure;
    private $httpOnly;

    private static $dateFormats = [
        'D, d M Y H:i:s T',
        'D, d-M-y H:i:s T',
        'D, d-M-Y H:i:s T',
        'D, d-m-y H:i:s T',
        'D, d-m-Y H:i:s T',
        'D M j G:i:s Y',
        'D M d H:i:s Y T'
    ];

    public function __construct(
        string $name,
        string $value,
        int $expires = null,
        string $path = null,
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->expires = $expires;
        $this->path = $path ?: '/';
        $this->domain = \strtolower($domain);
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getExpirationTime() {
        return $this->expires;
    }

    public function isExpired(): bool {
        return $this->expires && $this->expires < time();
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getDomain(): string {
        return $this->domain;
    }

    public function isSecure(): bool {
        return $this->secure;
    }

    public function isHttpOnly(): bool {
        return $this->httpOnly;
    }

    public function withName(string $name): self {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function withValue(string $value): self {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }

    public function withExpirationTime(int $value = null): self {
        $clone = clone $this;
        $clone->expires = $value;

        return $clone;
    }

    public function withPath(string $path): self {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    public function withDomain(string $domain): self {
        $clone = clone $this;
        $clone->domain = $domain;

        return $clone;
    }

    public function withSecure(bool $secure): self {
        $clone = clone $this;
        $clone->secure = $secure;

        return $clone;
    }

    public function withHttpOnly(bool $httpOnly): self {
        $clone = clone $this;
        $clone->httpOnly = $httpOnly;

        return $clone;
    }

    public function __toString(): string {
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

    /**
     * @param string $rawCookieStr
     *
     * @return self
     *
     * @link https://tools.ietf.org/html/rfc6265#section-5.2
     */
    public static function fromString(string $rawCookieStr): self {
        if ($rawCookieStr === "") {
            throw new CookieFormatException(
                $rawCookieStr,
                "Empty cookie string"
            );
        }

        $parts = explode(';', trim($rawCookieStr));
        $nvPair = array_shift($parts);

        if (strpos($nvPair, '=') === false) {
            throw new CookieFormatException(
                $rawCookieStr,
                "Missing '=' to separate name and value"
            );
        }

        list($name, $value) = explode('=', $nvPair, 2);

        if (\trim($name) === "") {
            throw new CookieFormatException($rawCookieStr, "Empty name");
        }

        $attrStruct = [
            'expires' => null,
            'path' => '',
            'domain' => "",
            'secure' => false,
            'httponly' => false,
            'max-age' => null
        ];

        foreach ($parts as $part) {
            $part = \trim($part);
            if (0 === \stripos($part, 'secure')) {
                $attrStruct['secure'] = true;
                continue;
            } elseif (0 === \stripos($part, 'httponly')) {
                $attrStruct['httponly'] = true;
                continue;
            }

            if (\strpos($part, '=') === false) {
                $attr = $part;
                $attrValue = "1";
            } else {
                list($attr, $attrValue) = explode('=', $part, 2);
            }

            $attr = strtolower($attr);
            if (array_key_exists($attr, $attrStruct)) {
                $attrStruct[$attr] = trim($attrValue, "\"\t\n\r\0\x0B\x20");
            }
        }

        $attrStruct['httponly'] = (bool) $attrStruct['httponly'];
        $attrStruct['secure'] = (bool) $attrStruct['secure'];

        if (isset($attrStruct['max-age']) && intval($attrStruct['max-age']) == $attrStruct['max-age']) {
            $attrStruct['expires'] = time() + $attrStruct['max-age'];
        } elseif ($attrStruct['expires']) {
            $attrStruct['expires'] = self::parseDate($attrStruct['expires']);
        }

        return new self(
            \trim($name),
            \trim($value),
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

        throw new CookieFormatException(
            $dateStr,
            'Invalid expires attribute'
        );
    }
}
