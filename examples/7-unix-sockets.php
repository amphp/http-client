<?php

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketClient;
use Amp\Loop;
use Amp\Socket\StaticSocketPool;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    try {
        // Unix sockets require a socket pool that changes all URLs to a fixed one.
        $socketPool = new StaticSocketPool("unix:///var/run/docker.sock");
        $client = new SocketClient($socketPool);

        // Artax currently requires a host, so just use a dummy one.
        $request = new Request('http://docker/info');
        $promise = $client->request($request);

        /** @var Response $response */
        $response = yield $promise;

        \printf(
            "HTTP/%s %d %s\n\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );

        $body = yield $response->getBody();
        print $body . "\n";
    } catch (HttpException $error) {
        echo $error;
    }
});
