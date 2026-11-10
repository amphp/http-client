<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpContent;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\StreamedContent;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Pipeline;
use Amp\Socket;
use League\Uri;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;
use function Amp\Http\Client\events;

class Http1ConnectionTest extends AsyncTestCase
{
    public function testConnectionBusyAfterRequestIsIssued(): void
    {
        [$client, $server] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        async(fn () => $stream->request($request, new NullCancellation))->ignore();
        $stream = null; // gc instance

        self::assertNull($connection->getStream($request));
    }

    public function testConnectionBusyWithoutRequestButNotGarbageCollected(): void
    {
        [$client, $server] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        events()->requestStart($request);

        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = $connection->getStream($request);

        self::assertNull($connection->getStream($request));
    }

    public function testConnectionNotBusyWithoutRequestGarbageCollected(): void
    {
        [$client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        events()->requestStart($request);

        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = $connection->getStream($request);
        unset($stream); // gc instance

        delay(0); // required to clear instance in async :-(

        $secondRequest = new Request('http://localhost');
        events()->requestStart($secondRequest);
        self::assertNotNull($connection->getStream($secondRequest));
    }

    public function testIdleKeepAliveConnectionCanBeGarbageCollected(): void
    {
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);
        $connectionRef = \WeakReference::create($connection);

        $request = new Request('http://localhost');
        events()->requestStart($request);

        $stream = $connection->getStream($request);
        $server->write("HTTP/1.1 204 No Content\r\nConnection: keep-alive\r\nContent-Length: 0\r\n\r\n");

        $response = $stream->request($request, new NullCancellation);
        $response->getBody()->buffer();

        unset($client, $connection, $request, $response, $stream);

        do {
            delay(0);
        } while (\gc_collect_cycles());

        self::assertNull($connectionRef->get());

        $server->close();
    }

    public function test100Continue(): void
    {
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        $request = new Request('http://httpbin.org/post', 'POST');
        $request->setHeader('expect', '100-continue');

        events()->requestStart($request);
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
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        $socketData = "Data that should be sent after the upgrade response";

        $invoked = false;
        $callback = function (Socket\Socket $socket, Request $request, Response $response) use (
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

        events()->requestStart($request);
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
        $this->setTimeout(1);

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null);

        $request = new Request('http://localhost');
        $request->setTransferTimeout(0.5);

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\ntest");

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request should have timed out");
        } catch (StreamException $exception) {
            self::assertStringContainsString('transfer timeout', $exception->getMessage());
        }
    }

    public function testInactivityTimeout(): void
    {
        $this->setMinimumRuntime(0.5);
        $this->setTimeout(1.5);

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null);

        $request = new Request('http://localhost');
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\n");

        EventLoop::unreference(EventLoop::delay(0.4, function () use ($server) {
            $server->write("test"); // Still missing 4 bytes from the body
        }));

        EventLoop::unreference(EventLoop::delay(1.25, function () use ($server) {
            try {
                $server->write("test"); // Request should timeout before this is called
            } catch (StreamException) {
                // ignore
            }
        }));

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request should have timed out");
        } catch (StreamException $exception) {
            self::assertStringContainsString('Inactivity timeout', $exception->getMessage());
        }
    }

    public function testWritingRequestWithRelativeUriPathFails(): void
    {
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        $request = new Request(Uri\Http::new('foo'));

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Relative path (foo) is not allowed in the request URI');

        $stream->request($request, new NullCancellation);
    }

    /**
     * @throws Socket\SocketException
     * @dataProvider providerValidUriPaths
     */
    public function testWritingRequestWithValidUriPathProceedsWithMatchingUriPath(
        string $requestPath,
        string $expectedPath
    ): void {
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);
        $uri = Uri\Http::new('http://localhost')->withPath($requestPath);
        $request = new Request($uri);
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
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

    public function testHeadResponseWithContentLengthHasNoBody(): void
    {
        $response = $this->receiveResponse(
            new Request('http://localhost', 'HEAD'),
            "HTTP/1.1 200 OK\r\nContent-Length: 42\r\n\r\n",
        );

        self::assertSame(200, $response->getStatus());
        self::assertSame('', $response->getBody()->buffer());
    }

    public function testResponseWithTrailers(): void
    {
        $response = $this->receiveResponse(
            new Request('http://localhost'),
            "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nTrailers: Expires\r\n\r\n"
            . "d\r\nHello, World!\r\n0\r\nExpires: date\r\n\r\n",
        );

        self::assertSame(200, $response->getStatus());
        self::assertSame('Expires', $response->getHeader('trailers'));
        self::assertSame('Hello, World!', $response->getBody()->buffer());
        self::assertSame('date', $response->getTrailers()->await()->getHeader('expires'));
    }

    public function testDisallowedTrailerIsRejected(): void
    {
        $response = $this->receiveResponse(
            new Request('http://localhost'),
            "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n"
            . "d\r\nHello, World!\r\n0\r\nContent-Length: 13\r\n\r\n",
        );

        try {
            $response->getBody()->buffer();
            self::fail('The response body should have failed to parse');
        } catch (StreamException $exception) {
            self::assertSame('HTTP response did not complete: Invalid trailers', $exception->getMessage());
        }

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid trailers');

        $response->getTrailers()->await();
    }

    public function testChunkExtensionsAreIgnored(): void
    {
        $response = $this->receiveResponse(
            new Request('http://localhost'),
            "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n"
            . "7;name=value\r\nHello, \r\n"
            . "6 ; flag ; q=\"a;\\\"b\"\r\nWorld!\r\n"
            . "0;last\r\n\r\n",
        );

        self::assertSame(200, $response->getStatus());
        self::assertSame('Hello, World!', $response->getBody()->buffer());
    }

    /**
     * @dataProvider provideInvalidChunkSizeLines
     */
    public function testInvalidChunkSizeLineIsRejected(string $sizeLine): void
    {
        $response = $this->receiveResponse(
            new Request('http://localhost'),
            "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n{$sizeLine}hello\r\n0\r\n\r\n",
        );

        try {
            $response->getBody()->buffer();
            self::fail('The response body should have failed to parse');
        } catch (StreamException $exception) {
            self::assertSame('HTTP response did not complete: Invalid chunk size line', $exception->getMessage());
            self::assertInstanceOf(ParseException::class, $exception->getPrevious());
        }
    }

    public function provideInvalidChunkSizeLines(): array
    {
        return [
            'empty extension' => ["5;\r\n"],
            'missing extension name' => ["5;=v\r\n"],
            'unterminated quoted-string' => ["5;n=\"v\r\n"],
            'space in token value' => ["5;n=a b\r\n"],
            'control character in extension' => ["5;n\x01\r\n"],
            'bare LF in extension' => ["5;n\n\r\n"],
            'whitespace only' => [" \r\n"],
            'extension without size' => [";x\r\n"],
            'whitespace and extension without size' => [" ;x\r\n"],
            'leading whitespace' => [" 5\r\n"],
            'bare LF before extension' => ["5\n;x\r\n"],
            'vertical tab after size' => ["5\x0b\r\n"],
            'non-hex size' => ["g\r\n"],
        ];
    }

    private function createSlowBody(): HttpContent
    {
        return StreamedContent::fromStream(new ReadableIterableStream(Pipeline::fromIterable(\array_fill(0, 100, '.'))->delay(0.1)));
    }

    private function receiveResponse(Request $request, string $response): Response
    {
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http1Connection($client, 0, null, 5);

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $server->write($response);

        return $stream->request($request, new NullCancellation);
    }
}
