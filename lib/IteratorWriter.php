<?php

namespace Amp\Artax;

use Amp\Reactor;
use Amp\Success;
use Amp\Future;
use Amp\Promise;

class IteratorWriter implements Writer {
    private $writerFactory;
    private $reactor;
    private $socket;
    private $iterator;
    private $future;
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
     * @param Reactor $reactor
     * @param resource $socket
     * @param mixed $iterator
     * @throws \DomainException On invalid iterator element.
     * @return \Amp\Promise
     */
    public function write(Reactor $reactor, $socket, $iterator) {
        if (!$iterator->valid()) {
            return new Success;
        }

        $this->reactor = $reactor;
        $this->socket = $socket;
        $this->iterator = $iterator;
        $this->future = $future = new Future($reactor);
        $this->writeNextElement();

        return $future->promise();
    }

    private function writeNextElement() {
        $current = $this->iterator->current();

        if (!$current instanceof Promise) {
            $this->finalizeEventualWriteElement($current);
            return;
        }

        $current->when(function($error, $result) {
            if ($error) {
                $this->future->fail($error);
            } else {
                $this->finalizeEventualWriteElement($result);
            }
        });
    }

    private function finalizeEventualWriteElement($current) {
        try {
            $this->writer = $this->writerFactory->make($current);
            $writePromise = $this->writer->write($this->reactor, $this->socket, $current);
            $writePromise->watch(function($update) {
                $this->future->update($update);
            });
            $writePromise->when(function($error, $result) {
                $this->afterElementWrite($error, $result);
            });
        } catch (\Exception $e) {
            // Protect against bad userland iterator return values from Iterator::current()
            $this->future->fail($e);
        }
    }

    private function afterElementWrite(\Exception $error = null, $result = null) {
        $this->iterator->next();

        if ($error) {
            $this->future->fail($error);
        } elseif ($this->iterator->valid()) {
            $this->writeNextElement();
        } else {
            $this->future->succeed();
        }
    }
}
