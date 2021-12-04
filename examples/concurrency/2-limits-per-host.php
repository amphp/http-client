<?php

use Amp\Cancellation;
use Amp\Future;
use Amp\Http\Client\Connection\ConnectionLimitingPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use function Amp\async;

require __DIR__ . '/../.helper/functions.php';

try {
    // Limit to one concurrent request per host
    $pool = ConnectionLimitingPool::byAuthority(1);

    $logger = new class implements NetworkInterceptor {
        public function requestViaNetwork(
            Request $request,
            Cancellation $cancellation,
            Stream $stream
        ): Response {
            print 'Starting request to ' . $request->getUri() . '...' . PHP_EOL;

            try {
                return $stream->request($request, $cancellation);
            } finally {
                print 'Done @ ' . $request->getUri() . ' ' . PHP_EOL;
            }
        }
    };

    $client = (new HttpClientBuilder)
        ->usingPool($pool)
        ->followRedirects(0)
        ->interceptNetwork($logger)
        ->build();

    for ($i = 0; $i < 3; $i++) {
        $futures = [];
        for ($j = 0; $j < 10; $j++) {
            $futures[] = async(static function () use ($client, $i, $j): void {
                $response = $client->request(new Request("https://amphp.org/$i.$j"));
                $response->getBody()->buffer();
            });
        }

        Future\all($futures);
    }
} catch (HttpException $error) {
    echo $error;
}
