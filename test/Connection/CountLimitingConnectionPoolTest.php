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

        $this->setTimeout(5000);
        $this->setMinimumRuntime(2000);

        yield [
            $client->request(new Request('http://httpbin.org/delay/1')),
            $client->request(new Request('http://httpbin.org/delay/1')),
        ];
    }

    public function testTwoConnections(): \Generator
    {
        $client = (new HttpClientBuilder)
            ->usingPool(CountLimitingConnectionPool::byAuthority(2))
            ->build();

        $this->setTimeout(2000);
        $this->setMinimumRuntime(1000);

        yield [
            $client->request(new Request('http://httpbin.org/delay/1')),
            $client->request(new Request('http://httpbin.org/delay/1')),
        ];
    }
}
