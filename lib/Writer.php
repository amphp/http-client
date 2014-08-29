<?php

namespace Artax;

use Alert\Reactor;

interface Writer {
    /**
     * Write the specified entity body data to the socket
     *
     * @param \Alert\Reactor $reactor
     * @param resource $socket
     * @param mixed $dataToWrite
     * @return \After\Promise
     */
    public function write(Reactor $reactor, $socket, $dataToWrite);
}
