<?php

use Amp\Artax\HttpSocketPool;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\CancellationToken;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\BasicSocketPool;
use Amp\Socket\ClientSocket;
use Amp\Socket\SocketPool;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    try {
        // Unix sockets require a socket pool that changes all URLs to a fixed one.
        $socketPool = new class implements SocketPool {
            private $pool;

            public function __construct() {
                $this->pool = new BasicSocketPool;
            }

            public function checkout(string $uri, CancellationToken $token = null): Promise {
                return $this->pool->checkout("unix:///var/run/docker.sock", $token);
            }

            public function checkin(ClientSocket $socket) {
                $this->pool->checkin($socket);
            }

            public function clear(ClientSocket $socket) {
                $this->pool->clear($socket);
            }
        };

        $client = new Amp\Artax\DefaultClient(null, new HttpSocketPool($socketPool));

        $request = new Request('http://docker/info');
        $promise = $client->request($request);

        /** @var Response $response */
        $response = yield $promise;

        printf(
            "HTTP/%s %d %s\n\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );

        $body = yield $response->getBody();
        print $body . "\n";
    } catch (Amp\Artax\HttpException $error) {
        echo $error;
    }
});
