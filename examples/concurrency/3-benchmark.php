<?php declare(strict_types=1);

// Usage -----------------------------------------------
// Default  (10 x 100    requests): php examples/concurrency/3-benchmark.php
// Infinite (10 x 100    requests): php examples/concurrency/3-benchmark.php 0
// Custom   (10 x $count requests): php examples/concurrency/3-benchmark.php $count

use Amp\Future;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use function Amp\async;
use function Amp\now;

require __DIR__ . '/../.helper/functions.php';

$count = (int) ($argv[1] ?? "1000");

// Disable peer verification (not recommended, but we use a random test certificate here)
$tlsContext = (new ClientTlsContext(''))
    ->withoutPeerVerification();

$connectContext = (new ConnectContext)
    ->withTlsContext($tlsContext);

$client = (new HttpClientBuilder)
    ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory(null, $connectContext)))
    ->build();

$handler = fn (int $count): Future => async(static function () use ($count, $client, $argv): void {
    for ($i = 0; $i < $count; $i++) {
        $request = new Request($argv[2] ?? 'https://localhost:1338/');
        $request->setTcpConnectTimeout(1000);
        $request->setTlsHandshakeTimeout(1000);
        $request->setTransferTimeout(1000);

        $response = $client->request($request);
        $response->getBody()->buffer();
    }
});

do {
    $start = now();

    $futures = [];
    for ($i = 0; $i < 10; $i++) {
        $futures[] = $handler($count === 0 ? 100 : $count);
    }
    Future\await($futures);

    $duration = now() - $start;
    print "Took {$duration} seconds for " . (($count === 0 ? 100 : $count) * 10) . " requests" . PHP_EOL;

    gc_collect_cycles();
    gc_mem_caches();

    print "Memory: " . (memory_get_usage(true) / 1000) . " kb" . PHP_EOL;
} while ($count === 0);
