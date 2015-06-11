<?php

namespace Amp\Artax;

use Amp\Promise;
use Amp\Deferred;

/**
 * Wraps Iterators to add chunk encoding for each element
 */
class ChunkingIterator implements \Iterator {
    private $iterator;
    private $isLastChunk = false;

    /**
     * @param \Iterator $iterator
     */
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
        } elseif ($current instanceof Promise) {
            $promisor = new Deferred;
            $current->when(function($error, $result) use ($promisor) {
                if ($error) {
                    $promisor->fail($error);
                } elseif (is_string($result)) {
                    $promisor->succeed($this->applyChunkEncoding($result));
                } else {
                    $promisor->fail(new \DomainException(
                        sprintf('Only string/Promise elements may be chunked; %s provided', gettype($result))
                    ));
                }
            });
            return $promisor->promise();
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
