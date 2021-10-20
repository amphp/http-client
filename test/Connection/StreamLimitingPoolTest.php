<?php

namespace Amp\Http\Client\Connection;

use Amp\Future;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\LocalKeyedMutex;
use function Amp\coroutine;

class StreamLimitingPoolTest extends AsyncTestCase
{
    public function testByHost(): void
    {
        $client = (new HttpClientBuilder)
            ->usingPool(StreamLimitingPool::byHost(new UnlimitedConnectionPool, new LocalKeyedMutex))
            ->build();

        $this->setTimeout(5);
        $this->setMinimumRuntime(2);

        Future\all([
            coroutine(fn () => $client->request(new Request('https://httpbin.org/delay/1'))),
            coroutine(fn () => $client->request(new Request('https://httpbin.org/delay/1'))),
        ]);
    }
}
