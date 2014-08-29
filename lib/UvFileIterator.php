<?php

namespace Artax;

/**
 * @TODO Actually implement non-blocking iteration with Uv prior to release
 * For now, just use the dumb blocking functionality so we can get everything
 * working.
 */
class UvFileIterator extends NaiveFileIterator {
    private $loop;
    public function __construct($path, UvReactor $reactor) {
        parent::__construct($path);
        $this->loop = $reactor->getUnderlyingLoop();
    }
}
