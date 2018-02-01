<?php

// Usage -----------------------------------------------
// Default  (10 x 100    requests): php examples/8-benchmark.php
// Infinite (10 x 100    requests): php examples/8-benchmark.php 0
// Custom   (10 x $count requests): php examples/8-benchmark.php $count

use Amp\Artax\Client;
use Amp\Artax\Response;
use Amp\Loop;
use function Amp\coroutine;

require __DIR__ . '/../vendor/autoload.php';

$count = (int) ($argv[1] ?? "100");

Loop::run(function () use ($count) {
    $client = new Amp\Artax\DefaultClient;
    $client->setOption(Client::OP_TRANSFER_TIMEOUT, 50000);

    $handler = coroutine(function (int $count) use ($client) {
        for ($i = 0; $i < $count; $i++) {
            /** @var Response $response */
            $response = yield $client->request('http://localhost:1337/');
            yield $response->getBody();
        }
    });

    while ($count === 0) {
        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $handler($count === 0 ? 100 : $count);
        }
        yield $promises;

        print "Memory: " . (memory_get_usage(true) / 1000) . PHP_EOL;
    }
});
