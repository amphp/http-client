<?php

namespace Amp\Artax\Cookie;

class CookieParser {
    private static $dateFormats = array(
        'D, d M Y H:i:s T',
        'D, d-M-y H:i:s T',
        'D, d-M-Y H:i:s T',
        'D, d-m-y H:i:s T',
        'D, d-m-Y H:i:s T',
        'D M j G:i:s Y',
        'D M d H:i:s Y T'
    );

    public static function parse($rawCookieStr) {
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
