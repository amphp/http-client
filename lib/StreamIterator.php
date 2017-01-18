<?php

namespace Amp\Artax;

abstract class StreamIterator implements \Iterator {
    protected $readSize = 32768;
    public function setReadSize(int $int) {
        $this->readSize = $int;
    }
}
