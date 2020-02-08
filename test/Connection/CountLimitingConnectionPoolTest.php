<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\PHPUnit\AsyncTestCase;

class CountLimitingConnectionPoolTest extends AsyncTestCase
{
    public function testSingleConnection(): \Generator
    {
        $client = (new HttpClientBuilder)
            ->usingPool(CountLimitingConnectionPool::byAuthority(1))
            ->build();

        $this->setTimeout(10000);
        $this->setMinimumRuntime(6000);

        yield [
            $client->request(new Request('http://httpbin.org/delay/3')),
            $client->request(new Request('http://httpbin.org/delay/3')),
        ];
    }

    public function testTwoConnections(): \Generator
    {
        $client = (new HttpClientBuilder)
            ->usingPool(CountLimitingConnectionPool::byAuthority(2))
            ->build();

        $this->setTimeout(4000);
        $this->setMinimumRuntime(3000);

        yield [
            $client->request(new Request('http://httpbin.org/delay/3')),
            $client->request(new Request('http://httpbin.org/delay/3')),
        ];
    }
}
