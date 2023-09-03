<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\CancelledException;
use Amp\Future;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2Processor;
use Amp\Http\HttpStatus;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
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
    public static function packFrame(string $data, int $type, int $flags, int $stream = 0): string
    {
        return Http2Parser::compileFrame($data, $type, $flags, $stream);
    }

    public function test100Continue(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", (string) HttpStatus::CONTINUE],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $server->write(self::packFrame($hpack->encode([
            [":status", (string) HttpStatus::NO_CONTENT],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(204, $response->getStatus());
    }

    public function testSwitchingProtocols(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        events()->requestStart($request);

        /** @var Stream $stream */
        $stream = $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", (string) HttpStatus::SWITCHING_PROTOCOLS],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Switching Protocols (101) is not part of HTTP/2');

        $stream->request($request, new NullCancellation);
    }

    public function testTrailers(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        events()->requestStart($request);

        $stream = $connection->getStream($request);

        EventLoop::queue(static function () use ($server, $hpack): void {
            delay(0.1);

            $server->write(self::packFrame($hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["trailers", "Foo"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $server->write(self::packFrame('test', Http2Parser::DATA, 0, 1));

            delay(0.1);

            $server->write(self::packFrame($hpack->encode([
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
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        events()->requestStart($request);

        $stream = $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", (string) HttpStatus::OK],
            ["content-length", "4"],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        $trailers = $response->getTrailers()->await();

        self::assertSame([], $trailers->getHeaders());
    }

    public function testCancellingWhileStreamingBody(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        events()->requestStart($request);

        $stream = $connection->getStream($request);

        EventLoop::queue(static function () use ($server, $hpack) {
            delay(0.1);

            $server->write(self::packFrame($hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new TimeoutCancellation(0.5));

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (CancelledException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $server->read();
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
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setTransferTimeout(0.5);

        events()->requestStart($request);

        $stream = $connection->getStream($request);

        EventLoop::queue(static function () use ($server, $hpack) {
            delay(0.1);

            $server->write(self::packFrame($hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (TimeoutException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $server->read();
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
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('https://localhost/');

        $request->setPushHandler(function (Request $request, Future $future) use (&$pushPromise): void {
            $this->assertSame('/static', $request->getUri()->getPath());
            $pushPromise = $future;
        });

        events()->requestStart($request);

        $stream = $connection->getStream($request);

        EventLoop::queue(static function () use ($server, $hpack) {
            delay(0.1);

            $server->write(self::packFrame($hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            $server->write(self::packFrame(\pack("N", 2) . $hpack->encode([
                    [":method", 'GET'],
                    [":authority", 'localhost'],
                    [":scheme", 'https'],
                    [":path", '/static'],
                ]), Http2Parser::PUSH_PROMISE, Http2Parser::END_HEADERS, 1));

            $server->write(self::packFrame($hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 2));

            delay(0.1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

            delay(1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 2));
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
            $buffer = $server->read();
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
        $hpack = new HPack;

        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        EventLoop::queue(static function () use ($server, $hpack) {
            delay(0.1);

            $server->write(self::packFrame($hpack->encode([
                [":status", (string) HttpStatus::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(0.1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellation);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (TimeoutException $exception) {
            delay(0.01); // Allow frame queue to complete writing.
            $buffer = $server->read();
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
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);
        $server->write($frame = self::packFrame('', Http2Parser::SETTINGS, 0, 0));
        $connection->initialize();

        $request = new Request(new LaminasUri('foo'));
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Relative path (foo) is not allowed in the request URI');

        $stream->request($request, new NullCancellation);
    }

    public function testServerPushingOddStream(): void
    {
        [$server, $client] = Socket\createSocketPair();

        $hpack = new HPack;

        $connection = new Http2Connection($client, 0, null);
        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));
        $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(0.5);
        $request->setPushHandler($this->createCallback(0));

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $future = async(fn () => $stream->request($request, new NullCancellation));

        $server->write(self::packFrame($hpack->encode([
            [":status", (string) HttpStatus::OK],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        delay(0.01);

        $server->write(self::packFrame(\pack("N", 3) . $hpack->encode([
                [":method", 'GET'],
                [":authority", 'localhost'],
                [":scheme", 'http'],
                [":path", '/static'],
            ]), Http2Parser::PUSH_PROMISE, Http2Parser::END_HEADERS, 1));

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Invalid server initiated stream');

        /** @var Response $response */
        $response = $future->await();
        $response->getBody()->buffer();
    }

    public function testServerStreamRefuse(): void
    {
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);
        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0));
        $connection->initialize();

        $request = new Request('http://localhost/');
        events()->requestStart($request);
        $stream = $connection->getStream($request);

        async(static function () use ($server) {
            delay(0.1);

            $server->write(self::packFrame(\pack("N", Http2Parser::REFUSED_STREAM), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1));
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
        [$server, $client] = Socket\createSocketPair();

        $connection = new Http2Connection($client, 0, null);
        $server->write($frame = self::packFrame('', Http2Parser::SETTINGS, 0));
        $connection->initialize();

        $uri = Uri\Http::createFromString('http://localhost')->withPath($requestPath);
        $request = new Request($uri);
        $request->setInactivityTimeout(0.5);

        events()->requestStart($request);
        $stream = $connection->getStream($request);

        $future = async(fn () => $stream->request($request, new NullCancellation));
        $data = \substr($server->read(), \strlen(Http2Parser::PREFACE)); // cut off the HTTP/2 preface
        $data .= $server->read(); // Second read for header frame.
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
}
