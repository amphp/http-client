<?php

namespace Amp\Artax;

use Amp\Postponed;
use Amp\Success;
use Interop\Async\Promise;

class IteratorWriter implements Writer {
    private $writerFactory;
    private $socket;
    private $iterator;
    private $postponed;
    private $writer;

    /**
     * @param \Amp\Artax\WriterFactory $writerFactory
     */
    public function __construct(WriterFactory $writerFactory = null) {
        $this->writerFactory = $writerFactory ?: new WriterFactory;
    }

    /**
     * Write iterator content to the socket.
     *
     * @param resource $socket
     * @param mixed $iterator
     * @throws \DomainException On invalid iterator element.
     * @return \Amp\Observable
     */
    public function write($socket, $iterator) {
        if (!$iterator->valid()) {
            return new Success;
        }

        $this->socket = $socket;
        $this->iterator = $iterator;
        $this->postponed = new Postponed;
        $this->writeNextElement();

        return $this->postponed->observe();
    }

    private function writeNextElement() {
        $current = $this->iterator->current();

        if (!$current instanceof Promise) {
            $this->finalizeEventualWriteElement($current);
            return;
        }

        $current->when(function($error, $result) {
            if ($error) {
                $this->postponed->fail($error);
            } else {
                $this->finalizeEventualWriteElement($result);
            }
        });
    }

    private function finalizeEventualWriteElement($current) {
        try {
            $this->writer = $this->writerFactory->make($current);
            $writePromise = $this->writer->write($this->socket, $current);
            $writePromise->subscribe(function($update) {
                $this->postponed->emit($update);
            });
            $writePromise->when(function($error, $result) {
                $this->afterElementWrite($error, $result);
            });
        } catch (\Throwable $e) {
            // Protect against bad userland iterator return values from Iterator::current()
            $this->postponed->fail($e);
        }
    }

    private function afterElementWrite(\Throwable $error = null, $result = null) {
        $this->iterator->next();

        if ($error) {
            $this->postponed->fail($error);
        } elseif ($this->iterator->valid()) {
            $this->writeNextElement();
        } else {
            $this->postponed->resolve();
        }
    }
}
