<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\LocalKeyedMutex;

class StreamLimitingPoolTest extends AsyncTestCase
{
    public function testByHost(): \Generator
    {
        if (\PHP_VERSION_ID < 70400) {
            $this->markTestSkipped('Causes segfaults on PHP < 7.4');
        }

        $client = (new HttpClientBuilder)
            ->usingPool(StreamLimitingPool::byHost(new UnlimitedConnectionPool, new LocalKeyedMutex))
            ->build();

        $this->setTimeout(5000);
        $this->setMinimumRuntime(2000);

        yield [
            $client->request(new Request('https://httpbin.org/delay/1')),
            $client->request(new Request('https://httpbin.org/delay/1')),
        ];
    }
}
