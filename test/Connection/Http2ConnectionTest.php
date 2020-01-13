<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Status;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
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

        $this->expectException(HttpException::class);
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
}
