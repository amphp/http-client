<?php

namespace Amp\Artax;

use Amp\Reactor;
use Amp\Future;

class BufferWriter implements Writer {
    private $reactor;
    private $future;
    private $socket;
    private $buffer;
    private $writeWatcher;

    /**
     * Write specified $dataToWrite to the $socket destination stream
     *
     * @param \Amp\Reactor $reactor
     * @param resource $socket
     * @param string $dataToWrite
     * @return \Amp\Promise
     */
    public function write(Reactor $reactor, $socket, $dataToWrite) {
        $this->reactor = $reactor;
        $this->future = new Future($reactor);
        $this->socket = $socket;
        $this->buffer = $dataToWrite;
        $reactor->immediately(function() {
            $this->doWrite();
        });

        return $this->future->promise();
    }

    private function doWrite() {
        $bytesToWrite = strlen($this->buffer);
        $bytesWritten = @fwrite($this->socket, $this->buffer);

        if ($bytesToWrite === $bytesWritten) {
            $this->future->update($this->buffer);
            $this->succeed();
        } elseif (empty($bytesWritten) && $this->isSocketDead()) {
            $this->fail(new SocketException(
                'Socket disconnected prior to write completion :('
            ));
        } else {
            $notifyData = substr($this->buffer, 0, $bytesWritten);
            $this->buffer = substr($this->buffer, $bytesWritten);
            $this->future->update($notifyData);
            $this->enableWriteWatcher();
        }
    }

    private function isSocketDead() {
        return (!is_resource($this->socket) || @feof($this->socket));
    }

    private function fail(\Exception $e) {
        $this->future->fail($e);
        if ($this->writeWatcher) {
            $this->reactor->cancel($this->writeWatcher);
        }
    }

    private function succeed() {
        $this->future->succeed();
        if ($this->writeWatcher) {
            $this->reactor->cancel($this->writeWatcher);
        }
    }

    private function enableWriteWatcher() {
        if (empty($this->writeWatcher)) {
            $this->writeWatcher = $this->reactor->onWritable($this->socket, function() {
                $this->doWrite();
            });
        }
    }
}
