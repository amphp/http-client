<?php

namespace Amp\Artax\Cookie;

/** @internal */
final class PublicSuffixList {
    private static $initialized = false;
    private static $suffixPatterns;
    private static $exceptionPatterns;

    public static function isPublicSuffix($domain) {
        if (!self::$initialized) {
            self::readList();
            self::$initialized = true;
        }

        if (!self::isValidHostName($domain)) {
            $domain = \idn_to_ascii($domain);

            if (!self::isValidHostName($domain)) {
                throw new \InvalidArgumentException("Invalid host name: " . $domain);
            }
        }

        $domain = \implode(".", \array_reverse(\explode(".", \trim($domain, "."))));

        foreach (self::$exceptionPatterns as $pattern) {
            if (\preg_match($pattern, $domain)) {
                return false;
            }
        }

        foreach (self::$suffixPatterns as $pattern) {
            if (\preg_match($pattern, $domain)) {
                return true;
            }
        }

        return false;
    }

    private static function readList() {
        $lines = \file(__DIR__ . "/../../res/public_suffix_list.dat", \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

        $exceptions = [];
        $rules = [];

        foreach ($lines as $line) {
            if (\trim($line) === "") {
                continue;
            }

            if (\substr($line, 0, 2) === "//") {
                continue;
            }

            $rule = \strtok($line, " \t");

            if ($rule[0] === "!") {
                $exceptions[] = self::toRegex(\substr($rule, 1), true);
            } else {
                $rules[] = self::toRegex($rule, false);
            }
        }

        self::$exceptionPatterns = \array_map(function ($list) {
            return "(^(?:" . \implode("|", $list) . ")$)i";
        }, \array_chunk($exceptions, 256));

        self::$suffixPatterns = \array_map(function ($list) {
            return "(^(?:" . \implode("|", $list) . ")$)i";
        }, \array_chunk($rules, 256));
    }

    private static function toRegex($rule, $exception) {
        if (!self::isValidHostName(\strtr($rule, "*", "x"))) {
            $rule = \idn_to_ascii($rule);

            if (!self::isValidHostName(\strtr($rule, "*", "x"))) {
                \trigger_error("Invalid public suffix rule: " . $rule, \E_USER_DEPRECATED);

                return "";
            }
        }

        $regexParts = [];

        foreach (\explode(".", $rule) as $part) {
            if ($part === "*") {
                $regexParts[] = "[^.]+";
            } else {
                $regexParts[] = \preg_quote($part);
            }
        }

        $regex = \array_reduce($regexParts, function ($carry, $item) use ($exception) {
            if ($carry === "") {
                return $item;
            }

            return $item . "(?:\\." . $carry . ")" . ($exception ? "" : "?");
        }, "");

        return $regex;
    }

    private static function isValidHostName($name) {
        $pattern = <<<'REGEX'
/^(?<name>[a-z0-9]([a-z0-9-]*[a-z0-9])?)(\.(?&name))*$/i
REGEX;

        return !isset($name[253]) && \preg_match($pattern, $name);
    }
}