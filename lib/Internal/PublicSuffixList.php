<?php

namespace Amp\Artax\Internal;

use Amp\Uri\InvalidDnsNameException;
use function Amp\Uri\normalizeDnsName;

/** @internal */
final class PublicSuffixList {
    private static $initialized = false;
    private static $suffixPatterns;
    private static $exceptionPatterns;

    public static function isPublicSuffix(string $domain) {
        if (!self::$initialized) {
            self::readList();
            self::$initialized = true;
        }

        $domain = normalizeDnsName($domain);
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

            try {
                if ($rule[0] === "!") {
                    $exceptions[] = self::toRegex(\substr($rule, 1), true);
                } else {
                    $rules[] = self::toRegex($rule, false);
                }
            } catch (InvalidDnsNameException $e) {
                // ignore IDN rules if no IDN support is available
                // requests with IDNs will fail anyway then
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
        $labels = \explode(".", $rule);

        foreach ($labels as $key => $label) {
            if ($label !== "*") {
                $labels[$key] = normalizeDnsName($label);
            }
        }

        $rule = \implode(".", $labels);

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
}
