<?php

use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () use ($argv) {
    try {
        // There's no need to create a custom pool here, we just need it to access the statistics.
        $pool = new DefaultConnectionPool;

        $client = (new HttpClientBuilder)->usingPool($pool)->followRedirects(0)->build();

        /** @var Response $firstResponse */
        $firstResponse = yield $client->request(new Request($argv[1] ?? 'https://httpbin.org/user-agent'));

        \printf(
            "HTTP/%s %d %s\r\n\r\n",
            $firstResponse->getProtocolVersion(),
            $firstResponse->getStatus(),
            $firstResponse->getReason()
        );

        yield $firstResponse->getBody()->buffer();

        /** @var Response $secondResponse */
        $secondResponse = yield $client->request(new Request($argv[1] ?? 'https://httpbin.org/user-agent'));

        \printf(
            "HTTP/%s %d %s\r\n\r\n",
            $secondResponse->getProtocolVersion(),
            $secondResponse->getStatus(),
            $secondResponse->getReason()
        );

        yield $secondResponse->getBody()->buffer();

        print "Total connection attempts: " . $pool->getTotalConnectionAttempts() . "\r\n";
        print "Total stream requests: " . $pool->getTotalStreamRequests() . "\r\n";
        print "Currently open connections: " . $pool->getOpenConnectionCount() . "\r\n";
    } catch (HttpException $error) {
        echo $error;
    }
});
