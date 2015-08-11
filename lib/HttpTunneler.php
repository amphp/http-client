<?php

namespace Amp\Artax;

use Amp\Deferred;

class HttpTunneler {
    private static $READ_GRANULARITY = 32768;

    /**
     * Establish an HTTP tunnel to the specified authority over this socket
     *
     * @param resource $socket
     * @param string $authority
     * @return \Amp\Promise
     */
    public function tunnel($socket, $authority) {
        $struct = new HttpTunnelStruct;
        $struct->promisor = new Deferred;
        $struct->socket = $socket;
        $struct->writeBuffer = "CONNECT {$authority} HTTP/1.1\r\n\r\n";
        $this->doWrite($struct);

        return $struct->promisor->promise();
    }

    private function doWrite(HttpTunnelStruct $struct) {
        $socket = $struct->socket;
        $bytesToWrite = strlen($struct->writeBuffer);
        $bytesWritten = @fwrite($socket, $struct->writeBuffer);

        if ($bytesToWrite === $bytesWritten) {
            \Amp\cancel($struct->writeWatcher);
            $struct->parser = new Parser(Parser::MODE_RESPONSE);
            $struct->parser->enqueueResponseMethodMatch('CONNECT');
            $struct->readWatcher = \Amp\onReadable($socket, function() use ($struct) {
                $this->doRead($struct);
            });
        } elseif ($bytesWritten > 0) {
            $struct->writeBuffer = substr($struct->writeBuffer, 0, $bytesWritten);
            $this->enableWriteWatcher($struct);
        } elseif ($this->isSocketDead($socket)) {
            \Amp\cancel($struct->writeWatcher);
            $struct->promisor->fail(new SocketException(
                'Proxy CONNECT failed: socket went away while writing tunneling request'
            ));
        } else {
            $this->enableWriteWatcher($struct);
        }
    }

    private function isSocketDead($socketResource) {
        return (!is_resource($socketResource) || @feof($socketResource));
    }

    private function enableWriteWatcher(HttpTunnelStruct $struct) {
        if ($struct->writeWatcher === null) {
            $struct->writeWatcher = \Amp\onWritable($struct->socket, function() use ($struct) {
                $this->doWrite($struct);
            });
        }
    }

    private function doRead(HttpTunnelStruct $struct) {
        $socket = $struct->socket;
        $data = @fread($socket, self::$READ_GRANULARITY);
        if ($data != '') {
            $this->parseSocketData($struct, $data);
        } elseif ($this->isSocketDead($socket)) {
            \Amp\cancel($struct->readWatcher);
            $struct->promisor->fail(new SocketException(
                'Proxy CONNECT failed: socket went away while awaiting tunneling response'
            ));
        }
    }

    private function parseSocketData(HttpTunnelStruct $struct, $data) {
        try {
            $struct->parser->buffer($data);
            if (!$parsedResponseArr = $struct->parser->parse()) {
                return;
            }

            $status = $parsedResponseArr['status'];
            if ($status == 200) {
                // Tunnel connected! We're finished \o/ #WinningAtLife #DealWithIt
                stream_context_set_option($struct->socket, 'artax*', 'is_tunneled', true);
                $struct->promisor->succeed($struct->socket);
                \Amp\cancel($struct->readWatcher);
            } else {
                $struct->promisor->fail(new ClientException(
                    sprintf('Unexpected response status received from proxy: %d', $status)
                ));
                \Amp\cancel($struct->readWatcher);
            }
        } catch (ParseException $e) {
            \Amp\cancel($struct->readWatcher);
            $struct->promisor->fail(new ClientException(
                'Invalid HTTP response received from proxy while establishing tunnel',
                0,
                $e
            ));
        }
    }
}
