<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\PipelineStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Loop;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use Amp\Socket;
use Laminas\Diactoros\Uri as LaminasUri;
use League\Uri;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

class Http1ConnectionTest extends AsyncTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->ignoreLoopWatchers();
    }

    public function testConnectionBusyAfterRequestIsIssued(): void
    {
        [$client, $server] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        $stream = $connection->getStream($request);
        async(fn() => $stream->request($request, new NullCancellationToken));
        $stream = null; // gc instance

        $this->assertNull($connection->getStream($request));
    }

    public function testConnectionBusyWithoutRequestButNotGarbageCollected(): void
    {
        [$client, $server] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = $connection->getStream($request);

        $this->assertNull($connection->getStream($request));
    }

    public function testConnectionNotBusyWithoutRequestGarbageCollected(): void
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = $connection->getStream($request);
        /** @noinspection SuspiciousAssignmentsInspection */
        $stream = null; // gc instance

        delay(0); // required to clear instance in coroutine :-(

        $this->assertNotNull($connection->getStream($request));
    }

    public function test100Continue(): void
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://httpbin.org/post', 'POST');
        $request->setHeader('expect', '100-continue');

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 100 Continue\r\nFoo: Bar\r\n\r\nHTTP/1.1 204 Nothing to send\r\n\r\n");

        $response = $stream->request($request, new NullCancellationToken);

        $this->assertSame(204, $response->getStatus());
        $this->assertSame('Nothing to send', $response->getReason());

        $connection->close();
        $server->close();
        $client->close();
    }

    public function testUpgrade(): void
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

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

        $response = $stream->request($request, new NullCancellationToken);

        delay(0);

        $this->assertTrue($invoked);
        $this->assertSame(101, $response->getStatus());
        $this->assertSame('Switching Protocols', $response->getReason());
        $this->assertSame([], await($response->getTrailers())->getHeaders());
    }

    public function testTransferTimeout(): void
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(600);

        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client);

        $request = new Request('http://localhost');
        $request->setTransferTimeout(500);

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\ntest");

        $response = $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            $this->fail("The request should have timed out");
        } catch (TimeoutException $exception) {
            $this->assertStringContainsString('transfer timeout', $exception->getMessage());
        }
    }

    public function testInactivityTimeout(): void
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(1000);

        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client);

        $request = new Request('http://localhost');
        $request->setInactivityTimeout(500);

        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\n");

        Loop::unreference(Loop::delay(400, function () use ($server) {
            $server->write("test"); // Still missing 4 bytes from the body
        }));

        Loop::unreference(Loop::delay(1000, function () use ($server) {
            $server->write("test"); // Request should timeout before this is called
        }));

        $response = $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            $this->fail("The request should have timed out");
        } catch (TimeoutException $exception) {
            $this->assertStringContainsString('Inactivity timeout', $exception->getMessage());
        }
    }

    public function testWritingRequestWithRelativeUriPathFails(): void
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request(new LaminasUri('foo'));

        $stream = $connection->getStream($request);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Relative path (foo) is not allowed in the request URI');

        $stream->request($request, new NullCancellationToken);
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

        $connection = new Http1Connection($client, 5000);
        $uri = Uri\Http::createFromString('http://localhost')->withPath($requestPath);
        $request = new Request($uri);
        $request->setInactivityTimeout(500);

        $stream = $connection->getStream($request);

        $promise = async(fn() => $stream->request($request, new NullCancellationToken));
        $startLine = \explode("\r\n", $server->read())[0] ?? null;
        self::assertSame("GET {$expectedPath} HTTP/1.1", $startLine);

        try {
            await($promise);
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

            public function createBodyStream(): InputStream
            {
                return new PipelineStream(Pipeline\fromIterable(\array_fill(0, 100, '.'), 100));
            }

            public function getBodyLength(): ?int
            {
                return null;
            }
        };
    }
}
