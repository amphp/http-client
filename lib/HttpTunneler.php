<?php

namespace Amp\Artax;

use Amp\ByteStream\StreamException;
use Amp\Promise;
use Amp\Socket\Socket;
use function Amp\call;

class HttpTunneler {
    /**
     * Establish an HTTP tunnel to the specified authority over this socket.
     *
     * @param resource $socket
     * @param string   $authority
     *
     * @return Promise
     */
    public function tunnel($socket, string $authority): Promise {
        return call(function () use ($socket, $authority) {
            $socket = new Socket($socket);
            $parser = new Parser(null);
            $parser->enqueueResponseMethodMatch("CONNECT");

            try {
                yield $socket->write("CONNECT {$authority} HTTP/1.1\r\n\r\n");
            } catch (StreamException $e) {
                new SocketException(
                    'Proxy CONNECT failed: Socket went away while writing tunneling request', 0, $e
                );
            }

            try {
                while (null !== $chunk = yield $socket->read()) {
                    if (!$response = $parser->parse($chunk)) {
                        continue;
                    }

                    if ($response["status"] === 200) {
                        // Tunnel connected! We're finished \o/ #WinningAtLife #DealWithIt
                        \stream_context_set_option($socket->getResource(), 'artax*', 'is_tunneled', true);
                        return $socket->getResource();
                    } else {
                        throw new HttpException(\sprintf(
                            'Proxy CONNECT failed: Unexpected response status received from proxy: %d',
                            $response["status"]
                        ));
                    }
                }
            } catch (ParseException $e) {
                throw new HttpException(
                    'Proxy CONNECT failed: Malformed HTTP response received from proxy while establishing tunnel', 0, $e
                );
            } catch (StreamException $e) {
                // fall through
            }

            throw new SocketException(
                'Proxy CONNECT failed: Socket went away while awaiting tunneling response', 0, $e ?? null
            );
        });
    }
}
