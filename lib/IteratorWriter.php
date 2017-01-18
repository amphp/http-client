<?php

namespace Amp\Artax;

use Amp\{ Stream, Emitter, Success };
use AsyncInterop\Promise;

class IteratorWriter implements Writer {
    /** @var \Amp\Artax\WriterFactory */
    private $writerFactory;

    /** @var resource */
    private $socket;

    /** @var \Iterator */
    private $iterator;

    /** @var \Amp\Emitter */
    private $emitter;

    /** @var \Amp\Artax\Writer */
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
     * @param \Iterator $iterator
     * @throws \Error On invalid iterator element.
     * @return \Amp\Stream
     */
    public function write($socket, $iterator): Stream {
        if (!$iterator instanceof \Iterator) {
            throw new \TypeError("\$iterator must be an instance of \\Iterator");
        }

        if (!$iterator->valid()) {
            return new Success;
        }

        $this->socket = $socket;
        $this->iterator = $iterator;
        $this->emitter = new Emitter;
        $this->writeNextElement();

        return $this->emitter->stream();
    }

    private function writeNextElement() {
        $current = $this->iterator->current();

        if (!$current instanceof Promise) {
            $this->finalizeEventualWriteElement($current);
            return;
        }

        $current->when(function($error, $result) {
            if ($error) {
                $this->emitter->fail($error);
            } else {
                $this->finalizeEventualWriteElement($result);
            }
        });
    }

    private function finalizeEventualWriteElement($current) {
        try {
            $this->writer = $this->writerFactory->make($current);
            $writePromise = $this->writer->write($this->socket, $current);
            $writePromise->listen(function($update) {
                $this->emitter->emit($update);
            });
            $writePromise->when(function($error, $result) {
                $this->afterElementWrite($error, $result);
            });
        } catch (\Throwable $e) {
            // Protect against bad userland iterator return values from Iterator::current()
            $this->emitter->fail($e);
        }
    }

    private function afterElementWrite(\Throwable $error = null, $result = null) {
        $this->iterator->next();

        if ($error) {
            $this->emitter->fail($error);
        } elseif ($this->iterator->valid()) {
            $this->writeNextElement();
        } else {
            $this->emitter->resolve();
        }
    }
}
