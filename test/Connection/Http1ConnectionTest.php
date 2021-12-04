<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\PipelineStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use Amp\Socket;
use Laminas\Diactoros\Uri as LaminasUri;
use League\Uri;
use Revolt\EventLoop;
use function Amp\delay;
use function Amp\async;

class Http1ConnectionTest extends AsyncTestCase
{
    public function testConnectionBusyAfterRequestIsIssued(): void
    {
        [$client, $server] = Socket\createPair();

        $connection = new Http1Connection($client, 5);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        $stream = $connection->getStream($request);
        async(fn () => $stream->request($request, new NullCancellation))->ignore();
        $stream = null; // gc instance

        self::assertNull($connection->getStream($request));
    }

    public function testConnectionBusyWithoutRequestButNotGarbageCollected(): void
    {
        [$client, $server] = Socket\createPair();

        $connection = new Http1Connection($client, 5);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = $connection->getStream($request);

        self::assertNull($connection->getStream($request));
    }

    public function testConnectionNotBusyWithoutRequestGarbageCollected(): void
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = $connection->getStream($request);
        /** @noinspection SuspiciousAssignmentsInspection */
        $stream = null; // gc instance

        delay(0); // required to clear instance in async :-(

        self::assertNotNull($connection->getStream($request));
    }

    public function test100Continue(): void
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5);

        $request = new Request('http://httpbin.org/post', 'POST');
        $request->setHeader('expect', '100-continue');

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 100 Continue\r\nFoo: Bar\r\n\r\nHTTP/1.1 204 Nothing to send\r\n\r\n");

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(204, $response->getStatus());
        self::assertSame('Nothing to send', $response->getReason());

        $connection->close();
        $server->close();
        $client->close();
    }

    public function testUpgrade(): void
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5);

        $socketData = "Data that should be sent after the upgrade response";

        $invoked = false;
        $callback = function (Socket\EncryptableSocket $socket, Request $request, Response $response) use (
            &$invoked,
            $socketData
        ) {
            $invoked = true;
            $this->assertSame(101, $response->getStatus());
            $this->assertSame($socketData, $socket->read());
        };

        $request = new Request('http://httpbin.org/upgrade', 'GET');
        $request->setHeader('connection', 'upgrade');
        $request->setUpgradeHandler($callback);

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 101 Switching Protocols\r\nConnection: Upgrade\r\nUpgrade: test\r\n\r\n" . $socketData);

        $response = $stream->request($request, new NullCancellation);

        delay(0);

        self::assertTrue($invoked);
        self::assertSame(101, $response->getStatus());
        self::assertSame('Switching Protocols', $response->getReason());
        self::assertSame([], $response->getTrailers()->await()->getHeaders());
    }

    public function testTransferTimeout(): void
    {
        $this->setMinimumRuntime(0.5);
        $this->setTimeout(0.6);

        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client);

        $request = new Request('http://localhost');
        $request->setTransferTimeout(0.5);

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\ntest");

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request should have timed out");
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('transfer timeout', $exception->getMessage());
        }
    }

    public function testInactivityTimeout(): void
    {
        $this->setMinimumRuntime(0.5);
        $this->setTimeout(1);

        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client);

        $request = new Request('http://localhost');
        $request->setInactivityTimeout(0.5);

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\n");

        EventLoop::unreference(EventLoop::delay(0.4, function () use ($server) {
            $server->write("test")->ignore(); // Still missing 4 bytes from the body
        }));

        EventLoop::unreference(EventLoop::delay(1, function () use ($server) {
            $server->write("test")->ignore(); // Request should timeout before this is called
        }));

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request should have timed out");
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('Inactivity timeout', $exception->getMessage());
        }
    }

    public function testWritingRequestWithRelativeUriPathFails(): void
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5);

        $request = new Request(new LaminasUri('foo'));

        $stream = $connection->getStream($request);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Relative path (foo) is not allowed in the request URI');

        $stream->request($request, new NullCancellation);
    }

    /**
     * @param string $requestPath
     * @param string $expectedPath
     *
     * @throws Socket\SocketException
     * @dataProvider providerValidUriPaths
     */
    public function testWritingRequestWithValidUriPathProceedsWithMatchingUriPath(
        string $requestPath,
        string $expectedPath
    ): void {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5);
        $uri = Uri\Http::createFromString('http://localhost')->withPath($requestPath);
        $request = new Request($uri);
        $request->setInactivityTimeout(0.5);

        $stream = $connection->getStream($request);

        $future = async(fn () => $stream->request($request, new NullCancellation));
        $startLine = \explode("\r\n", $server->read())[0] ?? null;
        self::assertSame("GET {$expectedPath} HTTP/1.1", $startLine);

        try {
            $future->await();
        } catch (HttpException $exception) {
            $connection->close();
        }
    }

    public function providerValidUriPaths(): array
    {
        return [
            'Empty path is replaced with slash' => ['', '/'],
            'Absolute path is passed as is' => ['/foo', '/foo'],
        ];
    }

    private function createSlowBody()
    {
        return new class implements RequestBody {
            public function getHeaders(): array
            {
                return [];
            }

            public function createBodyStream(): ReadableStream
            {
                $pipeline = Pipeline\fromIterable(\array_fill(0, 100, '.'));
                $pipeline = $pipeline->pipe(Pipeline\delay(0.1));
                return new PipelineStream($pipeline);
            }

            public function getBodyLength(): ?int
            {
                return null;
            }
        };
    }
}
