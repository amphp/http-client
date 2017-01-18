<?php

namespace Amp\Artax;

use Amp\Stream;

interface Writer {
    /**
     * Write the specified entity body data to the socket
     *
     * @param resource $socket
     * @param mixed $dataToWrite
     * @return \Amp\Stream
     */
    public function write($socket, $dataToWrite): Stream;
}
