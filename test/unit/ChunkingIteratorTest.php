<?php

use Artax\ChunkingIterator;

class ChunkingIteratorTest extends PHPUnit_Framework_TestCase {
    
    function testIteratorChunksOutput() {
        $wrappedIter = new ChunkWrappedStubIterator;
        $chunkingIterator = new ChunkingIterator($wrappedIter);
        
        $buffer = '';
        while ($chunkingIterator->valid()) {
            $buffer .= $chunkingIterator->current();
            $chunkingIterator->current(); // cover cached current retrieval
            $chunkingIterator->next();
        }
        
        $expectedBuffer = '' .
            "3\r\n" .
            "one\r\n" .
            "3\r\n" .
            "two\r\n" .
            "5\r\n" .
            "three\r\n" .
            "0\r\n\r\n";
        
        $this->assertEquals($expectedBuffer, $buffer);
    }
    
}

class ChunkWrappedStubIterator implements \Iterator {
    
    private $position = 0;
    private $parts = [
        'one',
        'two',
        NULL,
        'three'
    ];

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->parts[$this->position];
    }

    function key() {
        return $this->position;
    }

    function next() {
        $this->position++;
    }

    function valid() {
        return array_key_exists($this->position, $this->parts);
    }
    
}
