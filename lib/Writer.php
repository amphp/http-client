<?php

namespace Amp\Artax;

interface Writer {
    /**
     * Write the specified entity body data to the socket
     *
     * @param resource $socket
     * @param mixed $dataToWrite
     * @return \Amp\Observable
     */
    public function write($socket, $dataToWrite);
}
