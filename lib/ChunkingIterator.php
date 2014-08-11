<?php

namespace Artax;

class ChunkingIterator implements \Iterator {
    private $iterator;
    private $isLastChunk = false;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function current() {
        if ($this->isLastChunk) {
            return $this->applyChunkEncoding('');
        } elseif (($current = $this->iterator->current()) === '') {
            return null;
        } elseif (is_string($current)) {
            return $this->applyChunkEncoding($current);
        } else {
            // @TODO How to react to an invalid type returned from an iterator?
            return null;
        }
    }

    private function applyChunkEncoding($chunk) {
        return dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
    }

    public function key() {
        return $this->iterator->key();
    }

    public function next() {
        $this->iterator->next();
    }

    public function valid() {
        if ($this->isLastChunk) {
            $isValid = $this->isLastChunk = false;
        } elseif (!$isValid = $this->iterator->valid()) {
            $this->isLastChunk = true;
            $isValid = true;
        }

        return $isValid;
    }

    public function rewind() {
        $this->isLastChunk = false;
        $this->iterator->rewind();
    }
}
