<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\LocalKeyedMutex;

class StreamLimitingConnectionPoolTest extends AsyncTestCase
{
    public function testByHost(): \Generator
    {
        $client = (new HttpClientBuilder)
            ->usingPool(StreamLimitingConnectionPool::byHost(new UnlimitedConnectionPool, new LocalKeyedMutex))
            ->build();

        $this->setTimeout(30000);
        $this->setMinimumRuntime(6000);

        yield [
            $client->request(new Request('https://httpbin.org/delay/3')),
            $client->request(new Request('https://httpbin.org/delay/3')),
        ];
    }
}
