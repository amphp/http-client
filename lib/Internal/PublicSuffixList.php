<?php

namespace Amp\Http\Client\Internal;

use Amp\Dns\InvalidNameException;
use function Amp\Dns\normalizeName;

/** @internal */
final class PublicSuffixList
{
    /**
     * @param string $domain
     *
     * @return bool
     *
     * @throws InvalidNameException
     */
    public static function isPublicSuffix(string $domain): bool
    {
        if (!self::$initialized) {
            self::readList();
            self::$initialized = true;
        }

        $domain = normalizeName($domain);
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

    private static function readList(): void
    {
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
            } catch (InvalidNameException $e) {
                // ignore IDN rules if no IDN support is available
                // requests with IDNs will fail anyway then
            }
        }

        self::$exceptionPatterns = \array_map(static function ($list) {
            return "(^(?:" . \implode("|", $list) . ")$)i";
        }, \array_chunk($exceptions, 256));

        self::$suffixPatterns = \array_map(static function ($list) {
            return "(^(?:" . \implode("|", $list) . ")$)i";
        }, \array_chunk($rules, 256));
    }

    /**
     * @param string $rule
     * @param bool   $exception
     *
     * @return string
     *
     * @throws InvalidNameException
     */
    private static function toRegex(string $rule, bool $exception): string
    {
        $labels = \explode(".", $rule);

        foreach ($labels as $key => $label) {
            if ($label !== "*") {
                $labels[$key] = normalizeName($label);
            }
        }

        $rule = \implode(".", $labels);

        $regexParts = [];

        foreach (\explode(".", $rule) as $part) {
            if ($part === "*") {
                $regexParts[] = "[^.]+";
            } else {
                /** @noinspection PregQuoteUsageInspection */ // We use (), so we don't have that problem
                $regexParts[] = \preg_quote($part);
            }
        }

        $regex = \array_reduce($regexParts, static function ($carry, $item) use ($exception) {
            if ($carry === "") {
                return $item;
            }

            return $item . "(?:\\." . $carry . ")" . ($exception ? "" : "?");
        }, "");

        return $regex;
    }

    private static $initialized = false;
    private static $suffixPatterns;
    private static $exceptionPatterns;
}
