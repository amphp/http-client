<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\StreamException;
use Amp\CancelledException;
use Amp\Future;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2Processor;
use Amp\Http\HttpStatus;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Amp\Socket\ResourceSocket;
use Amp\TimeoutCancellation;
use Laminas\Diactoros\Uri as LaminasUri;
use League\Uri;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;
use function Amp\Http\Client\events;
use function Amp\Http\formatDateHeader;

class Http2ConnectionTest extends AsyncTestCase
{
    private HPack $hpack;

    private ResourceSocket $server;
    private ResourceSocket $client;
    private Http2Connection $connection;

    public static function packFrame(
        string $data,
        int $type,
        int $flags = Http2Parser::NO_FLAG,
        int $stream = 0,
    ): string {
        return Http2Parser::compileFrame($data, $type, $flags, $stream);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->hpack = new HPack();

        [$this->server, $this->client] = Socket\createSocketPair();

        $this->connection = new Http2Connection($this->client, 0, null);
        $this->server->write(self::packFrame('', Http2Parser::SETTINGS, 0));
        $this->connection->initialize();
    }

    public function test100Continue(): void
    {
        $request = new Request('http://localhost/');

        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        $this->server->write(self::packFrame($this->hpack->encode([
            [":status", (string) HttpStatus::CONTINUE],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $this->server->write(self::packFrame($this->hpack->encode([
            [":status", (string) HttpStatus::NO_CONTENT],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(204, $response->getStatus());
    }

    public function testSwitchingProtocols(): void
    {
        $request = new Request('http://localhost/');

        events()->requestStart($request);

        /** @var Stream $stream */
        $stream = $this->connection->getStream($request);

        $this->server->write(self::packFrame($this->hpack->encode([
            [":status", (string) HttpStatus::SWITCHING_PROTOCOLS],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Switching Protocols (101) is not part of HTTP/2');

        $stream->request($request, new NullCancellation);
    }

    public function testTrailers(): void
    {
        $request = new Request('http://localhost/');

        events()->requestStart($request);

        $stream = $this->connection->getStream($request);

        EventLoop::queue(function (): void {
            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["trailers", "Foo"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, 0, 1));

            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                ["foo", 'bar'],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        /** @var Trailers $trailers */
        $trailers = $response->getTrailers()->await();

        self::assertSame('bar', $trailers->getHeader('foo'));
    }

    public function testTrailersWithoutTrailers(): void
    {
        $request = new Request('http://localhost/');

        events()->requestStart($request);

        $stream = $this->connection->getStream($request);

        $this->server->write(self::packFrame($this->hpack->encode([
            [":status", (string) HttpStatus::OK],
            ["content-length", "4"],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        $trailers = $response->getTrailers()->await();

        self::assertSame([], $trailers->getHeaders());
    }

    public function testCancellingWhileStreamingBody(): void
    {
        $request = new Request('http://localhost/');

        events()->requestStart($request);

        $stream = $this->connection->getStream($request);

        EventLoop::queue(function (): void {
            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new TimeoutCancellation(0.5));

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (CancelledException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $this->server->read();
            $expected = \bin2hex(self::packFrame(
                \pack("N", Http2Parser::CANCEL),
                Http2Parser::RST_STREAM,
                Http2Parser::NO_FLAG,
                1,
            ));
            self::assertStringEndsWith($expected, \bin2hex($buffer));
        }
    }

    public function testTimeoutWhileStreamingBody(): void
    {
        $request = new Request('http://localhost/');
        $request->setTransferTimeout(0.5);

        events()->requestStart($request);

        $stream = $this->connection->getStream($request);

        EventLoop::queue(function (): void {
            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (StreamException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $this->server->read();
            $expected = \bin2hex(self::packFrame(
                \pack("N", Http2Parser::CANCEL),
                Http2Parser::RST_STREAM,
                Http2Parser::NO_FLAG,
                1,
            ));
            self::assertStringContainsString($expected, \bin2hex($buffer));
        }
    }

    public function testCancellingPushPromiseBody(): void
    {
        $request = new Request('https://localhost/');

        $request->setPushHandler(function (Request $request, Future $future) use (&$pushPromise): void {
            $this->assertSame('/static', $request->getUri()->getPath());
            $pushPromise = $future;
        });

        events()->requestStart($request);

        $stream = $this->connection->getStream($request);

        EventLoop::queue(function (): void {
            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            $this->server->write(self::packFrame(\pack("N", 2) . $this->hpack->encode([
                    [":method", 'GET'],
                    [":authority", 'localhost'],
                    [":scheme", 'https'],
                    [":path", '/static'],
                ]), Http2Parser::PUSH_PROMISE, Http2Parser::END_HEADERS, 1));

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 2));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

            delay(1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 2));
        });

        $request->setTransferTimeout(0.5);

        $response = $stream->request($request, new NullCancellation());

        self::assertSame(200, $response->getStatus());

        self::assertSame('test', $response->getBody()->buffer());

        self::assertInstanceOf(Future::class, $pushPromise);

        try {
            $response = $pushPromise->await();
            \assert($response instanceof Response);
            $response->getBody()->buffer();
            self::fail("The push promise body should have been cancelled");
        } catch (CancelledException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $this->server->read();
            $expected = \bin2hex(self::packFrame(
                \pack("N", Http2Parser::CANCEL),
                Http2Parser::RST_STREAM,
                Http2Parser::NO_FLAG,
                2,
            ));
            self::assertStringEndsWith($expected, \bin2hex($buffer));
        }
    }

    public function testInactivityWhileStreamingBody(): void
    {
        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        EventLoop::queue(function (): void {
            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (StreamException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $this->server->read();
            $expected = \bin2hex(self::packFrame(
                \pack("N", Http2Parser::CANCEL),
                Http2Parser::RST_STREAM,
                Http2Parser::NO_FLAG,
                1,
            ));
            self::assertStringEndsWith($expected, \bin2hex($buffer));
        }
    }

    public function testWritingRequestWithRelativeUriPathFails(): void
    {
        $request = new Request(new LaminasUri('foo'));
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Relative path (foo) is not allowed in the request URI');

        $stream->request($request, new NullCancellation);
    }

    public function testServerPushingOddStream(): void
    {
        $this->hpack = new HPack;

        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(0.5);
        $request->setPushHandler($this->createCallback(0));

        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        $future = async(fn () => $stream->request($request, new NullCancellation));

        $this->server->write(self::packFrame($this->hpack->encode([
            [":status", (string) HttpStatus::OK],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        delay(0.01);

        $this->server->write(self::packFrame(\pack("N", 3) . $this->hpack->encode([
                [":method", 'GET'],
                [":authority", 'localhost'],
                [":scheme", 'http'],
                [":path", '/static'],
            ]), Http2Parser::PUSH_PROMISE, Http2Parser::END_HEADERS, 1));

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid server initiated stream');

        /** @var Response $response */
        $response = $future->await();
        $response->getBody()->buffer();
    }

    public function testServerStreamRefuse(): void
    {
        $request = new Request('http://localhost/');
        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        async(function () {
            delay(0.1);

            $this->server->write(self::packFrame(
                data: \pack("N", Http2Parser::REFUSED_STREAM),
                type: Http2Parser::RST_STREAM,
                stream: 1,
            ));
        });

        try {
            $stream->request($request, new NullCancellation());

            self::fail('SocketException expected');
        } catch (SocketException $socketException) {
            events()->requestFailed($request, $socketException);

            $this->assertSame('Stream closed by server: Stream refused', $socketException->getMessage());
            $this->assertTrue($request->isUnprocessed());
        }
    }

    /**
     * @throws Socket\SocketException
     * @throws \Amp\ByteStream\ClosedException
     * @throws \Amp\ByteStream\StreamException
     * @dataProvider providerValidUriPaths
     */
    public function testWritingRequestWithValidUriPathProceedsWithMatchingUriPath(
        string $requestPath,
        string $expectedPath
    ): void {
        $uri = Uri\Http::new('http://localhost')->withPath($requestPath);
        $request = new Request($uri);
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        $future = async(fn () => $stream->request($request, new NullCancellation));
        $data = \substr($this->server->read(), \strlen(Http2Parser::PREFACE)); // cut off the HTTP/2 preface
        $data .= $this->server->read(); // Second read for header frame.
        $processor = $this->createMock(Http2Processor::class);
        $expectedPseudo = [
            ':authority' => 'localhost',
            ':path' => $expectedPath,
            ':scheme' => 'http',
            ':method' => 'GET',
        ];
        $processor
            ->expects(self::once())
            ->method('handleHeaders')
            ->with(self::anything(), self::identicalTo($expectedPseudo), self::anything(), self::anything());
        $parser = new Http2Parser($processor, new HPack());
        $parser->push($data);

        try {
            $future->await();
        } catch (HttpException $exception) {
            $this->connection->close();
        }
    }

    public function providerValidUriPaths(): array
    {
        return [
            'Empty path is replaced with slash' => ['', '/'],
            'Absolute path is passed as is' => ['/foo', '/foo'],
        ];
    }

    public function testServerAbruptDisconnect(): void
    {
        $request = new Request('http://localhost/');
        events()->requestStart($request);
        $stream = $this->connection->getStream($request);

        EventLoop::queue(function (): void {
            delay(0.1);

            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            $this->server->close();
        });

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
        } catch (StreamException $exception) {
            self::assertStringContainsString('response did not complete', $exception->getMessage());

            $previous = $exception->getPrevious();
            self::assertInstanceOf(SocketException::class, $previous);
            self::assertStringContainsString('closed unexpectedly', $previous->getMessage());
        }
    }

    public function testServerGoAwayFrame(): void
    {
        $request1 = new Request('http://localhost/');
        events()->requestStart($request1);
        $stream1 = $this->connection->getStream($request1);

        $request2 = new Request('http://localhost/');
        events()->requestStart($request2);
        $stream2 = $this->connection->getStream($request2);

        $response1Future = async(fn () => $stream1->request($request1, new NullCancellation));
        $response2Future = async(fn () => $stream2->request($request2, new NullCancellation));

        EventLoop::queue(function (): void {
            $this->server->write(self::packFrame($this->hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $this->server->write(self::packFrame(
                data: \pack('N*', 1, Http2Parser::GRACEFUL_SHUTDOWN),
                type: Http2Parser::GOAWAY,
            ));

            delay(0.1);

            $this->server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

            delay(0.1);

            $this->server->close();
        });

        $response1 = $response1Future->await();
        self::assertSame(200, $response1->getStatus());
        self::assertSame('test', $response1->getBody()->buffer());

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Received GOAWAY frame');

        try {
            $response2 = $response2Future->await();
        } finally {
            $this->connection->close();
        }
    }
}
