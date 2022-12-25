<?php declare(strict_types=1);

use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

require __DIR__ . '/../.helper/functions.php';

try {
    // There's no need to create a custom pool here, we just need it to access the statistics.
    $pool = new UnlimitedConnectionPool;

    $client = (new HttpClientBuilder)
        ->usingPool($pool)
        ->followRedirects(0)
        ->build();

    $firstResponse = $client->request(new Request($argv[1] ?? 'https://httpbin.org/user-agent'));

    dumpResponseTrace($firstResponse);
    dumpResponseBodyPreview($firstResponse->getBody()->buffer());

    $secondResponse = $client->request(new Request($argv[1] ?? 'https://httpbin.org/user-agent'));

    dumpResponseTrace($secondResponse);
    dumpResponseBodyPreview($secondResponse->getBody()->buffer());

    print "Total connection attempts: " . $pool->getTotalConnectionAttempts() . "\r\n";
    print "Total stream requests: " . $pool->getTotalStreamRequests() . "\r\n";
    print "Currently open connections: " . $pool->getOpenConnectionCount() . "\r\n";
} catch (HttpException $error) {
    echo $error;
}
