<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\Internal\Http1Parser;
use Amp\Http\Client\Connection\Stream;
use Amp\PHPUnit\AsyncTestCase;

class ParserTest extends AsyncTestCase
{
    public function testKeepAliveHeadResponseParse(): void
    {
        $data = "HTTP/1.1 200 OK\r\nContent-Length: 42\r\n\r\n";
        $parser = new Http1Parser(
            new Request('/', 'HEAD'),
            $this->createMock(Stream::class),
            $this->createCallback(0),
            $this->createCallback(0)
        );

        $response = $parser->parse($data);
        while (!$parser->isComplete()) {
            $parser->parse();
        }

        $this->assertSame(200, $response->getStatus());
    }

    public function testResponseWithTrailers(): void
    {
        $callback = $this->createCallback(1, fn () => ['expires' => ['date']]);

        $data = "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nTrailers: Expires\r\n\r\nd\r\nHello, World!\r\n0\r\nExpires: date\r\n\r\n";
        $parser = new Http1Parser(
            new Request('/', 'GET'),
            $this->createMock(Stream::class),
            $this->createCallback(1),
            $callback
        );

        $response = $parser->parse($data);
        while (!$parser->isComplete()) {
            $parser->parse();
        }

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Expires', $response->getHeader('trailers'));
    }
}
