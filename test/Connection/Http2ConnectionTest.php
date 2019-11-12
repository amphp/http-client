<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\HPack;
use Amp\Http\Status;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use function Amp\Http\formatDateHeader;

class Http2ConnectionTest extends AsyncTestCase
{
    public static function packFrame(string $data, string $type, string $flags, int $stream = 0): string
    {
        return \substr(\pack("N", \strlen($data)), 1, 3) . $type . $flags . \pack("N", $stream) . $data;
    }

    public static function packHeader(
        array $headers,
        bool $continue = false,
        int $stream = 1,
        int $split = \PHP_INT_MAX
    ): string {
        $data = "";
        $hpack = new HPack;
        $headers = $hpack->encode($headers);
        $all = \str_split($headers, $split);
        if ($split !== PHP_INT_MAX) {
            $flag = Http2Connection::PADDED;
            $len = 1;
            $all[0] = \chr($len) . $all[0] . \str_repeat("\0", $len);
        } else {
            $flag = Http2Connection::NOFLAG;
        }

        $end = \array_pop($all);
        $type = Http2Connection::HEADERS;

        foreach ($all as $frame) {
            $data .= self::packFrame($frame, $type, $flag, $stream);
            $type = Http2Connection::CONTINUATION;
            $flag = Http2Connection::NOFLAG;
        }

        $flags = ($continue ? $flag : Http2Connection::END_STREAM | $flag) | Http2Connection::END_HEADERS;

        return $data . self::packFrame($end, $type, $flags, $stream);
    }

    public function test100Continue(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Connection::SETTINGS, Http2Connection::NOFLAG, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            ":status" => Status::CONTINUE,
            "date" => [formatDateHeader()],
        ]), Http2Connection::HEADERS, Http2Connection::END_HEADERS, 1));

        $server->write(self::packFrame($hpack->encode([
            ":status" => Status::NO_CONTENT,
            "date" => [formatDateHeader()],
        ]), Http2Connection::HEADERS, Http2Connection::END_HEADERS | Http2Connection::END_STREAM, 1));

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(204, $response->getStatus());
    }

    public function testSwitchingProtocols(): \Generator
    {
        $hpack = new HPack;

        [$server, $client] = Socket\createPair();

        $connection = new Http2Connection($client);

        $server->write(self::packFrame('', Http2Connection::SETTINGS, Http2Connection::NOFLAG, 0));

        yield $connection->initialize();

        $request = new Request('http://localhost/');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write(self::packFrame($hpack->encode([
            ":status" => Status::SWITCHING_PROTOCOLS,
            "date" => [formatDateHeader()],
        ]), Http2Connection::HEADERS, Http2Connection::END_HEADERS, 1));

        $this->expectException(Http2ConnectionException::class);
        $this->expectExceptionMessage('Switching Protocols (101) is not part of HTTP/2');

        yield $stream->request($request, new NullCancellationToken);
    }
}
