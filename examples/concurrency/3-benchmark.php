<?php

// Usage -----------------------------------------------
// Default  (10 x 100    requests): php examples/concurrency/3-benchmark.php
// Infinite (10 x 100    requests): php examples/concurrency/3-benchmark.php 0
// Custom   (10 x $count requests): php examples/concurrency/3-benchmark.php $count

use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use function Amp\coroutine;
use function Amp\getCurrentTime;

require __DIR__ . '/../.helper/functions.php';

$count = (int) ($argv[1] ?? "1000");

Loop::run(static function () use ($count, $argv) {
    // Disable peer verification (not recommended, but we use a random test certificate here)
    $tlsContext = (new ClientTlsContext(''))
        ->withoutPeerVerification();

    $connectContext = (new ConnectContext)
        ->withTlsContext($tlsContext);

    $client = (new HttpClientBuilder)
        ->usingPool(new UnlimitedConnectionPool(null, $connectContext))
        ->build();

    $handler = coroutine(static function (int $count) use ($client, $argv) {
        for ($i = 0; $i < $count; $i++) {
            $request = new Request($argv[2] ?? 'https://localhost:1338/');
            $request->setTcpConnectTimeout(1000);
            $request->setTlsHandshakeTimeout(1000);
            $request->setTransferTimeout(1000);

            /** @var Response $response */
            $response = yield $client->request($request);
            yield $response->getBody()->buffer();
        }
    });

    do {
        $start = getCurrentTime();

        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $handler($count === 0 ? 100 : $count);
        }
        yield $promises;

        $duration = getCurrentTime() - $start;
        print "Took {$duration} ms for " . (($count === 0 ? 100 : $count) * 10) . " requests" . PHP_EOL;

        \gc_collect_cycles();
        \gc_mem_caches();

        print "Memory: " . (\memory_get_usage(true) / 1000) . PHP_EOL;
    } while ($count === 0);
});
