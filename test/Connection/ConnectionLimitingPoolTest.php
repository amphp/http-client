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
use function Amp\asyncCall;
use function Amp\call;

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

    private function createMockClosableConnection(Request $request): Promise
    {
        $content = 'open';
        $busy = false;
        $closeHandlers = [];

        $stream = $this->createMock(Stream::class);
        $stream->method('request')
            ->willReturnCallback(static function () use (&$content, $request, &$busy) {
                return call(static function () use (&$content, $request, &$busy) {
                    // simulate a request taking some time
                    yield new Delayed(500);
                    $busy = false;
                    // we can't pass this as the value to Delayed because we need to capture $content after the delay completes
                    return new Response('1.1', 200, null, [], new InMemoryStream($content), $request, new Success(new Trailers([])));
                });
            });
        $stream->method('getLocalAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));
        $stream->method('getRemoteAddress')
            ->willReturn(new SocketAddress('127.0.0.1'));

        $connection = $this->createMock(Connection::class);
        $connection->method('getStream')
            ->willReturnCallback(static function () use (&$content, $stream, &$busy) {
                $result = new Delayed(1, $busy ? null : $stream);
                $busy = true;
                return $result;
            });
        $connection->method('getProtocolVersions')
            ->willReturn(['1.1', '1.0']);
        $connection->expects($this->atMost(1))
            ->method('close')
            ->willReturnCallback(static function () use (&$content, &$closeHandlers, $connection) {
                $content = 'closed';
                foreach ($closeHandlers as $closeHandler) {
                    asyncCall($closeHandler, $connection);
                }
                return new Success;
            });
        $connection->method('onClose')
            ->willReturnCallback(static function (callable $callback) use (&$closeHandlers) {
                $closeHandlers[] = $callback;
            });

        return new Delayed(1, $connection);
    }

    public function testConnectionNotClosedWhileInUse(): \Generator
    {
        $request = new Request('http://localhost');

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->method('create')
            ->willReturnCallback(function () use ($request) {
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
            $promises[] = $client->request($request);
        }
        yield $promises;

        // all requests have completed and all connections are now idle. run through the connections again.
        $promises = [];
        for ($i = 0; $i < $numRequests; $i++) {
            $promises[] = $client->request($request);
        }
        $responses = yield $promises;
        foreach ($responses as $response) {
            $data = yield $response->getBody()->buffer();
            // if $data === 'closed', the connection was closed before the request completed
            $this->assertNotSame('closed', $data);
        }
    }
}
