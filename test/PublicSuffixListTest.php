<?php

namespace Amp\Test\Artax\Cookie;

use Amp\Http\Client\Internal\PublicSuffixList;
use PHPUnit\Framework\TestCase;

class PublicSuffixListTest extends TestCase
{
    /**
     * @dataProvider provideTestData
     * @requires extension intl
     *
     * @param $domain
     * @param $expectation
     *
     * @throws \Amp\Dns\InvalidNameException
     */
    public function testWithData($domain, $expectation): void
    {
        $this->assertSame($expectation, PublicSuffixList::isPublicSuffix($domain));
    }

    public function provideTestData(): array
    {
        $lines = \file(__DIR__ . "/fixture/public_suffix_list_tests.txt", \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        $lines = \array_filter($lines, static function ($line) {
            return \substr($line, 0, 2) !== "//";
        });

        return \array_map(static function ($line) {
            $parts = \explode(" ", $line);

            return [
                $parts[0],
                (bool) $parts[1],
            ];
        }, $lines);
    }
}
