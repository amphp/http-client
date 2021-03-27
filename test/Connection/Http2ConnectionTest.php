<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\CancelledException;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2Processor;
use Amp\Http\Status;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\TimeoutCancellationToken;
use Laminas\Diactoros\Uri as LaminasUri;
use League\Uri;
use function Amp\async;
use function Amp\await;
use function Amp\Http\formatDateHeader;
use function Revolt\EventLoop\defer;
use function Revolt\EventLoop\delay;

class Http2ConnectionTest extends AsyncTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->ignoreLoopWatchers();
    }

    public static function packFrame(string $data, int $type, int $flags, int $stream = 0): string
    {
        return \substr(\pack("NccN", \strlen($data), $type, $flags, $stream), 1) . $data;
    }

    public function test100Continue(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        $stream = $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::CONTINUE],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::NO_CONTENT],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));

        $response = $stream->request($request, new NullCancellationToken);

        self::assertSame(204, $response->getStatus());
    }

    public function testSwitchingProtocols(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::SWITCHING_PROTOCOLS],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $this->expectException(Http2ConnectionException::class);
        $this->expectExceptionMessage('Switching Protocols (101) is not part of HTTP/2');

        $stream->request($request, new NullCancellationToken);
    }

    public function testTrailers(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        $stream = $connection->getStream($request);

        defer(static function () use ($server, $hpack): void {
            delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "4"],
                ["trailers", "Foo"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, 0, 1));

            delay(100);

            $server->write(self::packFrame($hpack->encode([
                ["foo", 'bar'],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellationToken);

        self::assertSame(200, $response->getStatus());

        /** @var Trailers $trailers */
        $trailers = await($response->getTrailers());

        self::assertSame('bar', $trailers->getHeader('foo'));
    }

    public function testTrailersWithoutTrailers(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        $stream = $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::OK],
            ["content-length", "4"],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

        $response = $stream->request($request, new NullCancellationToken);

        self::assertSame(200, $response->getStatus());

        $trailers = await($response->getTrailers());

        self::assertSame([], $trailers->getHeaders());
    }

    public function testCancellingWhileStreamingBody(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');

        $stream = $connection->getStream($request);

        defer(static function () use ($server, $hpack) {
            delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new TimeoutCancellationToken(500));

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (CancelledException $exception) {
            $buffer = $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1);
            self::assertStringEndsWith($expected, $buffer);
        }
    }

    public function testTimeoutWhileStreamingBody(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setTransferTimeout(500);

        $stream = $connection->getStream($request);

        defer(static function () use ($server, $hpack) {
            delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellationToken);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (TimeoutException $exception) {
            $buffer = $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1);
            self::assertStringContainsString($expected, $buffer);
        }
    }

    public function testCancellingPushPromiseBody(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('https://localhost/');

        $request->setPushHandler(function (Request $request, Promise $response) use (&$pushPromise): void {
            $this->assertSame('/static', $request->getUri()->getPath());
            $pushPromise = $response;
        });

        $stream = $connection->getStream($request);

        defer(static function () use ($server, $hpack) {
            delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
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
                [":status", Status::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 2));

            delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

            delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 2));
        });

        $response = $stream->request($request, new TimeoutCancellationToken(500));

        self::assertSame(200, $response->getStatus());

        self::assertSame('test', $response->getBody()->buffer());

        self::assertInstanceOf(Promise::class, $pushPromise);

        try {
            $response = await($pushPromise);
            \assert($response instanceof Response);
            $response->getBody()->buffer();
            self::fail("The push promise body should have been cancelled");
        } catch (CancelledException $exception) {
            $buffer = $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 2);
            self::assertStringEndsWith($expected, $buffer);
        }
    }

    public function testInactivityWhileStreamingBody(): void
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(500);

        $stream = $connection->getStream($request);

        defer(static function () use ($server, $hpack) {
            delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        $response = $stream->request($request, new NullCancellationToken);

        self::assertSame(200, $response->getStatus());

        try {
            $response->getBody()->buffer();
            self::fail("The request body should have been cancelled");
        } catch (TimeoutException $exception) {
            $buffer = $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1);
            self::assertStringEndsWith($expected, $buffer);
        }
    }

    public function testWritingRequestWithRelativeUriPathFails(): void
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);
        $server->write($frame = self::packFrame('', Http2Parser::SETTINGS, 0, 0));
        $connection->initialize();

        $request = new Request(new LaminasUri('foo'));
        $request->setInactivityTimeout(500);

        $stream = $connection->getStream($request);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Relative path (foo) is not allowed in the request URI');

        $stream->request($request, new NullCancellationToken);
    }

    public function testServerPushingOddStream(): void
    {
        [$server, $client] = Socket\createPair();

        $hpack = new HPack;

        $connection = new Http2Connection($client);
        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));
        $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(500);
        $request->setPushHandler($this->createCallback(0));

        $stream = $connection->getStream($request);

        $promise = async(fn () => $stream->request($request, new NullCancellationToken));

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::OK],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        delay(10);

        $server->write(self::packFrame(\pack("N", 3) . $hpack->encode([
            [":method", 'GET'],
            [":authority", 'localhost'],
            [":scheme", 'http'],
            [":path", '/static'],
        ]), Http2Parser::PUSH_PROMISE, Http2Parser::END_HEADERS, 1));

        $this->expectException(Http2ConnectionException::class);
        $this->expectExceptionMessage('Invalid server initiated stream');

        /** @var Response $response */
        $response = await($promise);
        $response->getBody()->buffer();
    }

    /**
     * @param string $requestPath
     * @param string $expectedPath
     * @return void
     * @throws Socket\SocketException
     * @throws \Amp\ByteStream\ClosedException
     * @throws \Amp\ByteStream\StreamException
     * @throws \Amp\Http\Http2\Http2ConnectionException
     * @dataProvider providerValidUriPaths
     */
    public function testWritingRequestWithValidUriPathProceedsWithMatchingUriPath(
        string $requestPath,
        string $expectedPath
    ): void {
        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);
        $server->write($frame = self::packFrame('', Http2Parser::SETTINGS, 0, 0));
        $connection->initialize();

        $uri = Uri\Http::createFromString('http://localhost')->withPath($requestPath);
        $request = new Request($uri);
        $request->setInactivityTimeout(500);

        $stream = $connection->getStream($request);

        $promise = async(fn () => $stream->request($request, new NullCancellationToken));
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
        $parser = (new Http2Parser($processor))->parse();
        $parser->send($data);

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
}
