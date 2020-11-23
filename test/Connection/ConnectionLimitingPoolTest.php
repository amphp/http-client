<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InMemoryStream;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\Trailers;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\SocketAddress;
use Amp\Success;
use function Amp\async;
use function Amp\await;
use function Amp\defer;
use function Amp\delay;

class ConnectionLimitingPoolTest extends AsyncTestCase
{
    public function testSingleConnection(): void
    {
        $client = (new HttpClientBuilder)
            ->usingPool(ConnectionLimitingPool::byAuthority(1))
            ->build();

        $this->setTimeout(5000);
        $this->setMinimumRuntime(2000);

        await([
            async(fn() => $client->request(new Request('http://httpbin.org/delay/1'))),
            async(fn() => $client->request(new Request('http://httpbin.org/delay/1'))),
        ]);
    }

    public function testTwoConnections(): void
    {
        $client = (new HttpClientBuilder)
            ->usingPool(ConnectionLimitingPool::byAuthority(2))
            ->build();

        $this->setTimeout(4000);
        $this->setMinimumRuntime(2000);

        await([
            async(fn() => $client->request(new Request('http://httpbin.org/delay/2'))),
            async(fn() => $client->request(new Request('http://httpbin.org/delay/2'))),
        ]);
    }

    private function createMockConnection(Request $request): Connection
    {
        $response = new Response('1.1', 200, null, [], new InMemoryStream, $request, new Success(new Trailers([])));

        $stream = $this->createMock(Stream::class);
        $stream->method('request')
            ->willReturnCallback(function () use ($response): Response {
                delay(100);
                return $response;
            });
        $stream->method('getLocalAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));
        $stream->method('getRemoteAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));

        $connection = $this->createMock(Connection::class);
        $connection->method('getStream')
            ->willReturn($stream);
        $connection->method('getProtocolVersions')
            ->willReturn(['1.1', '1.0']);

        return $connection;
    }

    public function testWaitForConnectionToBecomeAvailable(): void
    {
        $request = new Request('http://localhost');

        $connection = $this->createMockConnection($request);

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->exactly(1))
            ->method('create')
            ->willReturn($connection);

        $pool = ConnectionLimitingPool::byAuthority(1, $factory);

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        $this->setTimeout(250);

        await([
            async(fn() => $client->request($request)),
            async(fn() => $client->request($request)),
        ]);
    }

    public function testConnectionBecomingAvailableWhileConnecting(): void
    {
        $request = new Request('http://localhost');

        $connection = $this->createMockConnection($request);

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($connection): Connection {
                delay(500);
                return $connection;
            });

        $pool = ConnectionLimitingPool::byAuthority(2, $factory);

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        $this->setTimeout(750);

        await([
            async(fn() => $client->request($request)),
            async(fn() => $client->request($request)),
        ]);
    }

    private function createMockClosableConnection(Request $request): Connection
    {
        $content = 'open';
        $busy = false;
        $closeHandlers = [];

        $stream = $this->createMock(Stream::class);
        $stream->method('request')
            ->willReturnCallback(static function () use (&$content, $request, &$busy): Response {
                // simulate a request taking some time
                delay(500);
                $busy = false;
                // we can't pass this as the value to Delayed because we need to capture $content after the delay completes
                return new Response('1.1', 200, null, [], new InMemoryStream($content), $request, new Success(new Trailers([])));
            });
        $stream->method('getLocalAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));
        $stream->method('getRemoteAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));

        $connection = $this->createMock(Connection::class);
        $connection->method('getStream')
            ->willReturnCallback(static function () use (&$content, $stream, &$busy): ?Stream {
                delay(1);
                $result = $busy ? null : $stream;
                $busy = true;
                return $result;
            });
        $connection->method('getProtocolVersions')
            ->willReturn(['1.1', '1.0']);
        $connection->expects($this->atMost(1))
            ->method('close')
            ->willReturnCallback(static function () use (&$content, &$closeHandlers, $connection): void {
                $content = 'closed';
                foreach ($closeHandlers as $closeHandler) {
                    defer($closeHandler, $connection);
                }
            });
        $connection->method('onClose')
            ->willReturnCallback(static function (callable $callback) use (&$closeHandlers): void {
                $closeHandlers[] = $callback;
            });

        delay(1);

        return $connection;
    }

    public function testConnectionNotClosedWhileInUse(): void
    {
        $request = new Request('http://localhost');

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->method('create')
            ->willReturnCallback(function () use ($request): Connection {
                return $this->createMockClosableConnection($request);
            });

        $client = (new HttpClientBuilder)
            ->usingPool(new UnlimitedConnectionPool($factory))
            ->build();

        // perform some number of requests. because of the delay in creating the connection and the delay in executing
        // the request, the pool will have to open a new connection for each request.
        $numRequests = 66;
        $promises = [];
        for ($i = 0; $i < $numRequests; $i++) {
            $promises[] = async(fn() => $client->request($request));
        }
        await($promises);

        // all requests have completed and all connections are now idle. run through the connections again.
        $promises = [];
        for ($i = 0; $i < $numRequests; $i++) {
            $promises[] = async(fn() => $client->request($request));
        }
        $responses = await($promises);
        foreach ($responses as $response) {
            $data = $response->getBody()->buffer();
            // if $data === 'closed', the connection was closed before the request completed
            $this->assertNotSame('closed', $data);
        }
    }
}
