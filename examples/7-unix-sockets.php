<?php

use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Socket\DnsConnector;
use Amp\Socket\StaticConnector;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () {
    try {
        // Unix sockets require a socket pool that changes all URLs to a fixed one.
        $connector = new StaticConnector("unix:///var/run/docker.sock", new DnsConnector);
        $client = new Client(new DefaultConnectionPool($connector));

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

        $body = yield $response->getBody()->buffer();
        print $body . "\n";
    } catch (HttpException $error) {
        echo $error;
    }
});
