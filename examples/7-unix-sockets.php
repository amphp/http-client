<?php

use Amp\Artax\HttpSocketPool;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Loop;
use Amp\Socket\StaticSocketPool;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    try {
        // Unix sockets require a socket pool that changes all URLs to a fixed one.
        $socketPool = new StaticSocketPool("unix:///var/run/docker.sock");
        $client = new Amp\Artax\DefaultClient(null, new HttpSocketPool($socketPool));

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
    } catch (Amp\Artax\HttpException $error) {
        echo $error;
    }
});
