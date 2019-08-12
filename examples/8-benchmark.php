<?php

// Usage -----------------------------------------------
// Default  (10 x 100    requests): php examples/8-benchmark.php
// Infinite (10 x 100    requests): php examples/8-benchmark.php 0
// Custom   (10 x $count requests): php examples/8-benchmark.php $count

use Amp\Http\Client\Client;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\TimeoutCancellationToken;
use function Amp\coroutine;

require __DIR__ . '/../vendor/autoload.php';

$count = (int) ($argv[1] ?? "1000");

Loop::run(static function () use ($count) {
    $client = new Client;

    $handler = coroutine(static function (int $count) use ($client) {
        for ($i = 0; $i < $count; $i++) {
            /** @var Response $response */
            $response = yield $client->request(new Request('http://localhost:1337/'), new TimeoutCancellationToken(50000));
            yield $response->getBody()->buffer();
        }
    });

    do {
        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $handler($count === 0 ? 100 : $count);
        }
        yield $promises;

        \gc_collect_cycles();
        \gc_mem_caches();

        print "Memory: " . (\memory_get_usage(true) / 1000) . PHP_EOL;
    } while ($count === 0);
});
