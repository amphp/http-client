<?php

namespace Artax;

class ChunkingIterator implements \Iterator {
    
    private $iterator;
    private $isLastChunk = FALSE;
    
    function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }
    
    function current() {
        if ($this->isLastChunk) {
            $current = '';
        } elseif (($current = $this->iterator->current()) === '') {
            $current === NULL;
        }
        
        return ($current === NULL) ? $current : $this->applyChunkEncoding($current);
    }
    
    private function applyChunkEncoding($chunk) {
        return dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
    }
    
    function key() {
        return $this->iterator->key();
    }

    function next() {
        return $this->iterator->next();
    }

    function valid() {
        if ($this->isLastChunk) {
            $isValid = $this->isLastChunk = FALSE;
        } elseif (!$isValid = $this->iterator->valid()) {
            $this->isLastChunk = TRUE;
            $isValid = TRUE;
        }
        
        return $isValid;
    }

    function rewind() {
        $this->isLastChunk = FALSE;
        
        return $this->iterator->rewind();
    }
    
}

