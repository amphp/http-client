<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InMemoryStream;
use Amp\Delayed;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\Trailers;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Success;

class ConnectionLimitingPoolTest extends AsyncTestCase
{
    public function testSingleConnection(): \Generator
    {
        $client = (new HttpClientBuilder)
            ->usingPool(ConnectionLimitingPool::byAuthority(1))
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
            ->usingPool(ConnectionLimitingPool::byAuthority(2))
            ->build();

        $this->setTimeout(4000);
        $this->setMinimumRuntime(2000);

        yield [
            $client->request(new Request('http://httpbin.org/delay/2')),
            $client->request(new Request('http://httpbin.org/delay/2')),
        ];
    }

    private function createMockConnection(Request $request): Connection
    {
        $response = new Response('1.1', 200, null, [], new InMemoryStream, $request, new Success(new Trailers([])));

        $stream = $this->createMock(Stream::class);
        $stream->method('request')
            ->willReturn(new Delayed(100, $response));
        $stream->method('getLocalAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));
        $stream->method('getRemoteAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));

        $connection = $this->createMock(Connection::class);
        $connection->method('getStream')
            ->willReturn(new Success($stream));
        $connection->method('getProtocolVersions')
            ->willReturn(['1.1', '1.0']);

        return $connection;
    }

    public function testWaitForConnectionToBecomeAvailable(): \Generator
    {
        $request = new Request('http://localhost');

        $connection = $this->createMockConnection($request);

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->exactly(1))
            ->method('create')
            ->willReturn(new Success($connection));

        $pool = ConnectionLimitingPool::byAuthority(1, $factory);

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        $this->setTimeout(250);

        yield [$client->request($request), $client->request($request)];
    }

    public function testConnectionBecomingAvailableWhileConnecting(): \Generator
    {
        $request = new Request('http://localhost');

        $connection = $this->createMockConnection($request);

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($connection): Promise {
                return new Delayed(500, $connection);
            });

        $pool = ConnectionLimitingPool::byAuthority(2, $factory);

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        $this->setTimeout(750);

        yield [$client->request($request), $client->request($request)];
    }
}
