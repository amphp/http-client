<?php

namespace Artax;

use Alert\Reactor,
    After\Deferred;

class HttpTunneler {
    const READ_GRANULARITY = 32768;
    
    private $reactor;

    public function __construct(Reactor $reactor) {
        $this->reactor = $reactor;
    }

    /**
     * @return After\Promise
     */
    public function tunnel($socket, $authority) {
        $struct = new HttpTunnelStruct;
        $struct->deferred = new Deferred;
        $struct->socket = $socket;
        $struct->writeBuffer = "CONNECT {$authority} HTTP/1.1\r\n\r\n";
        $this->doWrite($struct);

        return $struct->deferred->promise();
    }

    private function doWrite(HttpTunnelStruct $struct) {
        $socket = $struct->socket;
        $bytesToWrite = strlen($struct->writeBuffer);
        $bytesWritten = @fwrite($socket, $struct->writeBuffer);

        if ($bytesToWrite === $bytesWritten) {
            $this->reactor->cancel($struct->writeWatcher);
            $struct->parser = new Parser(Parser::MODE_RESPONSE);
            $struct->parser->enqueueResponseMethodMatch('CONNECT');
            $struct->readWatcher = $this->reactor->onReadable($socket, function() use ($struct) {
                $this->doRead($struct);
            });
        } elseif ($bytesWritten > 0) {
            $struct->writeBuffer = substr($struct->writeBuffer, 0, $bytesWritten);
            $this->enableWriteWatcher($struct);
        } elseif ($this->isSocketDead($socket)) {
            $this->reactor->cancel($struct->writeWatcher);
            $struct->deferred->fail(new SocketException(
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
            $struct->writeWatcher = $this->reactor->onWritable($struct->socket, function() use ($struct) {
                $this->doWrite($struct);
            });
        }
    }

    private function doRead(HttpTunnelStruct $struct) {
        $socket = $struct->socket;
        $data = @fread($socket, self::READ_GRANULARITY);
        if ($data != '') {
            $this->parseSocketData($struct, $data);
        } elseif ($this->isSocketDead($socket)) {
            $this->reactor->cancel($struct->readWatcher);
            $struct->deferred->fail(new SocketException(
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
                $struct->deferred->succeed($struct->socket);
                $this->reactor->cancel($struct->readWatcher);
            } else {
                $struct->deferred->fail(new ClientException(
                    sprintf('Unexpected response status received from proxy: %d', $status)
                ));
                $this->reactor->cancel($struct->readWatcher);
            }
        } catch (ParseException $e) {
            $this->reactor->cancel($struct->readWatcher);
            $struct->deferred->fail(new ClientException(
                'Invalid HTTP response received from proxy while establishing tunnel',
                0,
                $e
            ));
        }
    }
}
