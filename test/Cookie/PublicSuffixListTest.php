<?php

namespace Amp\Test\Artax\Cookie;

use Amp\Artax\Cookie\PublicSuffixList;
use PHPUnit\Framework\TestCase;

class PublicSuffixListTest extends TestCase {
    /** @dataProvider provideTestData */
    public function testWithData($domain, $expectation) {
        $this->assertSame($expectation, PublicSuffixList::isPublicSuffix($domain));
    }

    public function provideTestData() {
        $lines = \file(__DIR__ . "/../fixture/public_suffix_list_tests.txt", \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        $lines = \array_filter($lines, function ($line) {
            return \substr($line, 0, 2) !== "//";
        });

        return \array_map(function ($line) {
            $parts = \explode(" ", $line);

            return [
                $parts[0],
                (bool) $parts[1],
            ];
        }, $lines);
    }
}
