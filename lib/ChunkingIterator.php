<?php

namespace Artax;

use After\Promise, After\Future;

class ChunkingIterator implements \Iterator {
    private $iterator;
    private $isLastChunk = false;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function current() {
        if ($this->isLastChunk) {
            $current = '';
            return $this->applyChunkEncoding("");
        } elseif (($current = $this->iterator->current()) === '') {
            return null;
        } elseif (is_string($current)) {
            return $this->applyChunkEncoding($current);
        } elseif ($current instanceof Future) {
            $chunkFuture = new Promise;
            $current->onResolution(function($future) use ($chunkFuture) {
                $this->onFutureChunk($iteratorFuture, $chunkFuture);
            });
            return $chunkFuture;
        }
    }

    private function onFutureChunk(Future $future, $chunkFuture) {
        if (!$future->succeeded()) {
            $chunkFuture->fail($future->getError());
            return;
        }

        $data = $future->getValue();
        if (is_string($data)) {
            $chunkFuture->succeed($this->applyChunkEncoding($data));
        } else {
            $chunkFuture->fail(new \DomainException(
                sprintf('Unexpected request body iterator element: %s', gettype($data))
            ));
        }
    }

    private function applyChunkEncoding($chunk) {
        return dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
    }

    public function key() {
        return $this->iterator->key();
    }

    public function next() {
        return $this->iterator->next();
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

        return $this->iterator->rewind();
    }
}
