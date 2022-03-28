<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\ReadableBuffer;
use Amp\Future;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\Trailers;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\InternetAddress;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

class ConnectionLimitingPoolTest extends AsyncTestCase
{
    public function testSingleConnection(): void
    {
        $client = (new HttpClientBuilder)
            ->usingPool(ConnectionLimitingPool::byAuthority(1))
            ->build();

        $this->setTimeout(5);
        $this->setMinimumRuntime(2);

        Future\all([
            async(fn () => $client->request(new Request('http://httpbin.org/delay/1'))),
            async(fn () => $client->request(new Request('http://httpbin.org/delay/1'))),
        ]);
    }

    public function testTwoConnections(): void
    {
        $client = (new HttpClientBuilder)
            ->usingPool(ConnectionLimitingPool::byAuthority(2))
            ->build();

        $this->setTimeout(4);
        $this->setMinimumRuntime(2);

        Future\all([
            async(fn () => $client->request(new Request('http://httpbin.org/delay/2'))),
            async(fn () => $client->request(new Request('http://httpbin.org/delay/2'))),
        ]);
    }

    public function testWaitForConnectionToBecomeAvailable(): void
    {
        $request = new Request('http://localhost');

        $connection = $this->createMockConnection($request);

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects(self::exactly(1))
            ->method('create')
            ->willReturn($connection);

        $pool = ConnectionLimitingPool::byAuthority(1, $factory);

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        $this->setTimeout(0.25);

        Future\all([
            async(fn () => $client->request($request)),
            async(fn () => $client->request($request)),
        ]);
    }

    public function testConnectionBecomingAvailableWhileConnecting(): void
    {
        $request = new Request('http://localhost');

        $connection = $this->createMockConnection($request);

        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects(self::exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($connection): Connection {
                delay(0.5);
                return $connection;
            });

        $pool = ConnectionLimitingPool::byAuthority(2, $factory);

        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        $this->setTimeout(0.75);

        Future\all([
            async(fn () => $client->request($request)),
            async(fn () => $client->request($request)),
        ]);
    }

    public function testConnectionNotClosedWhileInUse(): void
    {
        $this->setTimeout(10);

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
            $promises[] = async(fn () => $client->request($request));
        }
        Future\all($promises);

        // all requests have completed and all connections are now idle. run through the connections again.
        $promises = [];
        for ($i = 0; $i < $numRequests; $i++) {
            $promises[] = async(fn () => $client->request($request));
        }
        $responses = Future\all($promises);
        foreach ($responses as $response) {
            $data = $response->getBody()->buffer();
            // if $data === 'closed', the connection was closed before the request completed
            self::assertNotSame('closed', $data);
        }
    }

    private function createMockConnection(Request $request): Connection
    {
        $response = new Response('1.1', 200, null, [], new ReadableBuffer, $request, Future::complete(new Trailers([])));

        $stream = $this->createMock(Stream::class);
        $stream->method('request')
            ->willReturnCallback(function () use ($response): Response {
                delay(0.1);
                return $response;
            });
        $stream->method('getLocalAddress')
            ->willReturn(new InternetAddress('127.0.0.1', 80));
        $stream->method('getRemoteAddress')
            ->willReturn(new InternetAddress('127.0.0.1', 80));

        $connection = $this->createMock(Connection::class);
        $connection->method('getStream')
            ->willReturn($stream);
        $connection->method('getProtocolVersions')
            ->willReturn(['1.1', '1.0']);

        return $connection;
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
                delay(0.5);
                $busy = false;
                // we can't pass this as the value to Delayed because we need to capture $content after the delay completes
                return new Response(
                    '1.1',
                    200,
                    null,
                    [],
                    new ReadableBuffer($content),
                    $request,
                    Future::complete(new Trailers([]))
                );
            });
        $stream->method('getLocalAddress')
            ->willReturn(new InternetAddress('127.0.0.1', 80));
        $stream->method('getRemoteAddress')
            ->willReturn(new InternetAddress('127.0.0.1', 80));

        $connection = $this->createMock(Connection::class);
        $connection->method('getStream')
            ->willReturnCallback(static function () use (&$content, $stream, &$busy): ?Stream {
                delay(0.01);
                $result = $busy ? null : $stream;
                $busy = true;
                return $result;
            });
        $connection->method('getProtocolVersions')
            ->willReturn(['1.1', '1.0']);
        $connection->expects(self::atMost(1))
            ->method('close')
            ->willReturnCallback(static function () use (&$content, &$closeHandlers, $connection): void {
                $content = 'closed';
                foreach ($closeHandlers as $closeHandler) {
                    EventLoop::queue($closeHandler, $connection);
                }
            });
        $connection->method('onClose')
            ->willReturnCallback(static function (callable $callback) use (&$closeHandlers): void {
                $closeHandlers[] = $callback;
            });

        delay(0);

        return $connection;
    }
}
