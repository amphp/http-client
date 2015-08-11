<?php

namespace Amp\Artax;

use Amp\Success;
use Amp\Deferred;
use Amp\Promise;

class IteratorWriter implements Writer {
    private $writerFactory;
    private $socket;
    private $iterator;
    private $promisor;
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
     * @return \Amp\Promise
     */
    public function write($socket, $iterator) {
        if (!$iterator->valid()) {
            return new Success;
        }

        $this->socket = $socket;
        $this->iterator = $iterator;
        $this->promisor = new Deferred;
        $this->writeNextElement();

        return $this->promisor->promise();
    }

    private function writeNextElement() {
        $current = $this->iterator->current();

        if (!$current instanceof Promise) {
            $this->finalizeEventualWriteElement($current);
            return;
        }

        $current->when(function($error, $result) {
            if ($error) {
                $this->promisor->fail($error);
            } else {
                $this->finalizeEventualWriteElement($result);
            }
        });
    }

    private function finalizeEventualWriteElement($current) {
        try {
            $this->writer = $this->writerFactory->make($current);
            $writePromise = $this->writer->write($this->socket, $current);
            $writePromise->watch(function($update) {
                $this->promisor->update($update);
            });
            $writePromise->when(function($error, $result) {
                $this->afterElementWrite($error, $result);
            });
        } catch (\Exception $e) {
            // Protect against bad userland iterator return values from Iterator::current()
            $this->promisor->fail($e);
        }
    }

    private function afterElementWrite(\Exception $error = null, $result = null) {
        $this->iterator->next();

        if ($error) {
            $this->promisor->fail($error);
        } elseif ($this->iterator->valid()) {
            $this->writeNextElement();
        } else {
            $this->promisor->succeed();
        }
    }
}
