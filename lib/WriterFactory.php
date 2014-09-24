<?php

namespace Amp\Artax;

class WriterFactory {
    /**
     * Generate an appropriate writer given the $body type
     *
     * @param $body
     * @return \Amp\Artax\Writer
     * @throws \DomainException
     */
    public function make($body) {
        if (is_string($body)) {
            return new BufferWriter;
        } elseif ($body instanceof \Iterator) {
            return new IteratorWriter($this);
        } else {
            throw new \DomainException(
                sprintf('Invalid write subject: %s. String or Iterator required', gettype($body))
            );
        }
    }
}
