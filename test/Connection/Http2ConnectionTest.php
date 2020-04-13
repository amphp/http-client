<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\CancelledException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Status;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\TimeoutCancellationToken;
use function Amp\asyncCall;
use function Amp\delay;
use function Amp\Http\formatDateHeader;

class Http2ConnectionTest extends AsyncTestCase
{
    public static function packFrame(string $data, int $type, int $flags, int $stream = 0): string
    {
        return \substr(\pack("NccN", \strlen($data), $type, $flags, $stream), 1) . $data;
    }

    public function test100Continue(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::CONTINUE],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::NO_CONTENT],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(204, $response->getStatus());
    }

    public function testSwitchingProtocols(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::SWITCHING_PROTOCOLS],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $this->expectException(Http2ConnectionException::class);
        $this->expectExceptionMessage('Switching Protocols (101) is not part of HTTP/2');

        yield $stream->request($request, new NullCancellationToken);
    }

    public function testTrailers(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        asyncCall(static function () use ($server, $hpack) {
            yield delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "4"],
                ["trailers", "Foo"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            yield delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, 0, 1));

            yield delay(100);

            $server->write(self::packFrame($hpack->encode([
                ["foo", 'bar'],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS | Http2Parser::END_STREAM, 1));
        });

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        /** @var Trailers $trailers */
        $trailers = yield $response->getTrailers();

        $this->assertSame('bar', $trailers->getHeader('foo'));
    }

    public function testTrailersWithoutTrailers(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            [":status", Status::OK],
            ["content-length", "4"],
            ["date", formatDateHeader()],
        ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

        $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        /** @var Trailers $trailers */
        $trailers = yield $response->getTrailers();

        $this->assertSame([], $trailers->getHeaders());
    }

    public function testCancellingWhileStreamingBody(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        asyncCall(static function () use ($server, $hpack) {
            yield delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            yield delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            yield delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        /** @var Response $response */
        $response = yield $stream->request($request, new TimeoutCancellationToken(500));

        $this->assertSame(200, $response->getStatus());

        try {
            yield $response->getBody()->buffer();
            $this->fail("The request body should have been cancelled");
        } catch (CancelledException $exception) {
            $buffer = yield $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1);
            $this->assertStringEndsWith($expected, $buffer);
        }
    }

    public function testTimeoutWhileStreamingBody(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setTransferTimeout(500);

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        asyncCall(static function () use ($server, $hpack) {
            yield delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            yield delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            yield delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        try {
            yield $response->getBody()->buffer();
            $this->fail("The request body should have been cancelled");
        } catch (TimeoutException $exception) {
            $buffer = yield $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1);
            $this->assertStringEndsWith($expected, $buffer);
        }
    }

    public function testCancellingPushPromiseBody(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('https://localhost/');

        $request->setPushHandler(function (Request $request, Promise $response) use (&$pushPromise): void {
            $this->assertSame('/static', $request->getUri()->getPath());
            $pushPromise = $response;
        });

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        asyncCall(static function () use ($server, $hpack) {
            yield delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            $server->write(self::packFrame(\pack("N", 3) . $hpack->encode([
                [":method", 'GET'],
                [":authority", 'localhost'],
                [":scheme", 'https'],
                [":path", '/static'],
            ]), Http2Parser::PUSH_PROMISE, Http2Parser::END_HEADERS, 1));

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "4"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 3));

            yield delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));

            yield delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 3));
        });

        /** @var Response $response */
        $response = yield $stream->request($request, new TimeoutCancellationToken(500));

        $this->assertSame(200, $response->getStatus());

        $this->assertSame('test', yield $response->getBody()->buffer());

        $this->assertInstanceOf(Promise::class, $pushPromise);

        try {
            $response = yield $pushPromise;
            \assert($response instanceof Response);
            yield $response->getBody()->buffer();
            $this->fail("The push promise body should have been cancelled");
        } catch (CancelledException $exception) {
            $buffer = yield $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 3);
            $this->assertStringEndsWith($expected, $buffer);
        }
    }

    public function testInactivityWhileStreamingBody(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Parser::SETTINGS, 0, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');
        $request->setInactivityTimeout(500);

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        asyncCall(static function () use ($server, $hpack) {
            yield delay(100);

            $server->write(self::packFrame($hpack->encode([
                [":status", Status::OK],
                ["content-length", "8"],
                ["date", formatDateHeader()],
            ]), Http2Parser::HEADERS, Http2Parser::END_HEADERS, 1));

            yield delay(100);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::NO_FLAG, 1));

            yield delay(1000);

            $server->write(self::packFrame('test', Http2Parser::DATA, Http2Parser::END_STREAM, 1));
        });

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        try {
            yield $response->getBody()->buffer();
            $this->fail("The request body should have been cancelled");
        } catch (TimeoutException $exception) {
            $buffer = yield $server->read();
            $expected = self::packFrame(\pack("N", Http2Parser::CANCEL), Http2Parser::RST_STREAM, Http2Parser::NO_FLAG, 1);
            $this->assertStringEndsWith($expected, $buffer);
        }
    }
}
