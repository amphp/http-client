<?php

namespace Amp\Artax;

use Amp\Deferred;

class BufferWriter implements Writer {
    private $promisor;
    private $socket;
    private $buffer;
    private $writeWatcher;
    private $bytesWritten = 0;

    /**
     * Write specified $dataToWrite to the $socket destination stream
     *
     * @param resource $socket
     * @param string $dataToWrite
     * @return \Amp\Promise
     */
    public function write($socket, $dataToWrite) {
        $this->promisor = new Deferred;
        $this->socket = $socket;
        $this->buffer = $dataToWrite;
        \Amp\immediately(function() {
            $this->doWrite();
        });

        return $this->promisor->promise();
    }

    private function doWrite() {
        $bytesToWrite = strlen($this->buffer);
        $bytesWritten = @fwrite($this->socket, $this->buffer);
        $this->bytesWritten += $bytesWritten;

        if ($bytesToWrite === $bytesWritten) {
            $this->promisor->update($this->buffer);
            $this->succeed();
        } elseif (empty($bytesWritten) && $this->isSocketDead()) {
            $this->fail(new SocketException(
                $this->generateWriteFailureMessage()
            ));
        } else {
            $notifyData = substr($this->buffer, 0, $bytesWritten);
            $this->buffer = substr($this->buffer, $bytesWritten);
            $this->promisor->update($notifyData);
            $this->enableWriteWatcher();
        }
    }

    private function generateWriteFailureMessage() {
        $sockContext = @stream_context_get_options($this->socket);
        if ($this->bytesWritten === 0 && empty($sockContext['ssl'])) {
            $msg = "Socket connection failed before data could be fully written. This *may* have ";
            $msg.= "occurred because you're attempting to connect via HTTP when the remote server ";
            $msg.= "only supports encrypted HTTPS connections. Try your request using an https:// URI.";
        } else {
            $msg = 'Connection to server severed before the request write could complete.';
        }

        return $msg;
    }

    private function isSocketDead() {
        return (!is_resource($this->socket) || @feof($this->socket));
    }

    private function fail(\Exception $e) {
        $this->promisor->fail($e);
        if ($this->writeWatcher) {
            \Amp\cancel($this->writeWatcher);
        }
    }

    private function succeed() {
        $this->promisor->succeed();
        if ($this->writeWatcher) {
            \Amp\cancel($this->writeWatcher);
        }
    }

    private function enableWriteWatcher() {
        if (empty($this->writeWatcher)) {
            $this->writeWatcher = \Amp\onWritable($this->socket, function() {
                $this->doWrite();
            });
        }
    }
}
