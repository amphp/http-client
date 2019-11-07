<?php

use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
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
        $client = (new HttpClientBuilder)->usingPool(new UnlimitedConnectionPool($connector))->build();

        // amphp/http-client requires a host, so just use a dummy one.
        $request = new Request('http://docker/info');

        /** @var Response $response */
        $response = yield $client->request($request);

        \printf(
            "HTTP/%s %d %s\r\n\r\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );

        $body = yield $response->getBody()->buffer();
        print $body . "\r\n";
    } catch (HttpException $error) {
        echo $error;
    }
});
