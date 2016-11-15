<?php

namespace Amp\Artax;

use Interop\Async\Promise;
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
            $deferred = new Deferred;
            $current->when(function($error, $result) use ($deferred) {
                if ($error) {
                    $deferred->fail($error);
                } elseif (is_string($result)) {
                    $deferred->resolve($this->applyChunkEncoding($result));
                } else {
                    $deferred->fail(new \DomainException(
                        sprintf('Only string/Promise elements may be chunked; %s provided', gettype($result))
                    ));
                }
            });
            return $deferred->promise();
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
