<?php

namespace Amp\Http\Client\Tunnel;

use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\PHPUnit\AsyncTestCase;
use Amp\ReactAdapter\ReactAdapter;
use Amp\Socket\SocketAddress;
use LeProxy\LeProxy\LeProxyServer;

class Http1TunnelTest extends AsyncTestCase
{
    public function test(): \Generator
    {
        $proxy = new LeProxyServer(ReactAdapter::get());
        $socket = $proxy->listen('127.0.0.1:0', false);

        $address = \str_replace('tcp://', '', $socket->getAddress());
        $connector = new Http1Tunnel(SocketAddress::fromSocketName($address));

        $client = (new HttpClientBuilder)
            ->usingPool(new UnlimitedConnectionPool($connector))
            ->build();

        $request = new Request('https://httpbin.org/headers');
        $request->setHeader('connection', 'close');

        /** @var Response $response */
        $response = yield $client->request($request);

        $this->assertSame(200, $response->getStatus());
        $this->assertJson(yield $response->getBody()->buffer());

        $socket->close();
    }
}
