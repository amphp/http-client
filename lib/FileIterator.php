<?php

namespace Artax;

abstract class FileIterator implements \Iterator {
    protected $readSize = 32768;
    public function setReadSize($int) {
        $this->readSize = (int) $int;
    }
}
