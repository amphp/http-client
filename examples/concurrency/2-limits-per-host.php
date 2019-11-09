<?php

use Amp\CancellationToken;
use Amp\Http\Client\Connection\LimitedConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Promise;
use Amp\Sync\LocalKeyedSemaphore;
use function Amp\call;

require __DIR__ . '/../.helper/functions.php';

Loop::run(static function () {
    try {
        // Limit to one concurrent request per host
        $pool = LimitedConnectionPool::byHost(new UnlimitedConnectionPool, new LocalKeyedSemaphore(1));

        $logger = new class implements NetworkInterceptor {
            public function requestViaNetwork(
                Request $request,
                CancellationToken $cancellation,
                Stream $stream
            ): Promise {
                return call(static function () use ($request, $cancellation, $stream) {
                    print 'Starting request to ' . $request->getUri() . '...' . PHP_EOL;

                    try {
                        return yield $stream->request($request, $cancellation);
                    } finally {
                        print 'Done @ ' . $request->getUri() . ' ' . PHP_EOL;
                    }
                });
            }
        };

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->followRedirects(0)
            ->interceptNetwork($logger)
            ->build();

        for ($i = 0; $i < 3; $i++) {
            $promises = [];
            for ($j = 0; $j < 10; $j++) {
                $promises[] = call(static function () use ($client, $i, $j) {
                    /** @var Response $response */
                    $response = yield $client->request(new Request("https://amphp.org/$i.$j"));
                    yield $response->getBody()->buffer();
                });
            }

            yield $promises;
        }
    } catch (HttpException $error) {
        echo $error;
    }
});
