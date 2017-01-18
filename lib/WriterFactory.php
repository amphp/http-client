<?php

namespace Amp\Artax;

class WriterFactory {
    /**
     * Generate an appropriate writer given the $body type
     *
     * @param $body
     * @return \Amp\Artax\Writer
     * @throws \Error
     */
    public function make($body): Writer {
        if (is_string($body)) {
            return new BufferWriter;
        } elseif ($body instanceof \Iterator) {
            return new IteratorWriter($this);
        } else {
            throw new \Error(
                sprintf('Invalid write subject: %s; string or instance of \Iterator required', gettype($body))
            );
        }
    }
}
