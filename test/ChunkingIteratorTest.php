<?php

namespace Amp\Test\Artax;

class ChunkingIteratorTest extends \PHPUnit_Framework_TestCase {
    public function testIteratorOutputChunking() {
        $iter = new \ArrayIterator([
            'aaa',
            'bb',
            'c'
        ]);

        $chunkingIter = new \Amp\Artax\ChunkingIterator($iter);
        $expected = "3\r\naaa\r\n2\r\nbb\r\n1\r\nc\r\n0\r\n\r\n";
        $actual = "";
        foreach ($chunkingIter as $chunk) {
            $actual .= $chunk;
        }

        $this->assertSame($expected, $actual);
    }

    public function testNullReturnedOnInvalidElement() {
        $iter = new \ArrayIterator([new \StdClass]);
        $chunkingIter = new \Amp\Artax\ChunkingIterator($iter);
        $this->assertNull($chunkingIter->current());
    }

    public function testFutureElementResolution() {
        $iter = new \ArrayIterator([
            'aaa',
            new \Amp\Success('bb'),
            'c'
        ]);
        $chunkingIter = new \Amp\Artax\ChunkingIterator($iter);
        $this->assertSame("3\r\naaa\r\n", $chunkingIter->current());
        $chunkingIter->next();
        $promise = $chunkingIter->current();
        $promise->when(function($error, $result) use ($chunkingIter) {
            $this->assertNull($error);
            $this->assertSame("2\r\nbb\r\n", $result);
            $chunkingIter->next();
            $this->assertSame("1\r\nc\r\n", $chunkingIter->current());
        });
    }
}
