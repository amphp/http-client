<?php

namespace Amp\Artax;

use Amp\Reactor;

interface Writer {
    /**
     * Write the specified entity body data to the socket
     *
     * @param \Amp\Reactor $reactor
     * @param resource $socket
     * @param mixed $dataToWrite
     * @return \Amp\Promise
     */
    public function write(Reactor $reactor, $socket, $dataToWrite);
}
