<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\Future;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Internal\Http1Parser;
use Amp\Http\Client\Connection\Stream;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;

class ParserTest extends AsyncTestCase
{
    public function testKeepAliveHeadResponseParse(): void
    {
        $data = "HTTP/1.1 200 OK\r\nContent-Length: 42\r\n\r\n";

        $request = new Request('/', 'HEAD');

        events()->requestStart($request);
        events()->connectionAcquired($request, $this->createMock(Connection::class), 1);
        events()->requestHeaderStart($request, $this->createMock(Stream::class));
        events()->requestHeaderEnd($request, $this->createMock(Stream::class));
        events()->requestBodyStart($request, $this->createMock(Stream::class));
        events()->requestBodyEnd($request, $this->createMock(Stream::class));

        $parser = new Http1Parser(
            $request,
            $this->createMock(Stream::class),
            $this->createCallback(0, fn () => Future::complete()),
            new NullCancellation(),
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

        $request = new Request('/', 'GET');

        events()->requestStart($request);
        events()->connectionAcquired($request, $this->createMock(Connection::class), 1);
        events()->requestHeaderStart($request, $this->createMock(Stream::class));
        events()->requestHeaderEnd($request, $this->createMock(Stream::class));
        events()->requestBodyStart($request, $this->createMock(Stream::class));
        events()->requestBodyEnd($request, $this->createMock(Stream::class));

        $data = "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nTrailers: Expires\r\n\r\nd\r\nHello, World!\r\n0\r\nExpires: date\r\n\r\n";
        $parser = new Http1Parser(
            $request,
            $this->createMock(Stream::class),
            $this->createCallback(1, fn () => Future::complete()),
            new NullCancellation(),
            $callback
        );

        $response = $parser->parse($data);
        while (!$parser->isComplete()) {
            $parser->parse();
        }

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Expires', $response->getHeader('trailers'));
    }

    public function testChunkExtensionsAreIgnored(): void
    {
        $data = "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n"
            . "7;name=value\r\nHello, \r\n"
            . "6 ; flag ; q=\"a;\\\"b\"\r\nWorld!\r\n"
            . "0;last\r\n\r\n";

        [$response, $body] = $this->parseChunked($data);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Hello, World!', $body);
    }

    public function testInvalidChunkExtensionIsRejected(): void
    {
        foreach (["5;\r\n", "5;=v\r\n", "5;n=\"v\r\n", "5;n=a b\r\n", "5;n\x01\r\n", "5;n\n\r\n"] as $sizeLine) {
            try {
                $this->parseChunked("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n{$sizeLine}hello\r\n0\r\n\r\n");
                $this->fail('Expected a ParseException for ' . \json_encode($sizeLine));
            } catch (ParseException $exception) {
                $this->assertSame('Invalid chunk extension', $exception->getMessage());
            }
        }
    }

    /**
     * @return array{Response, string}
     */
    private function parseChunked(string $data): array
    {
        $request = new Request('/', 'GET');

        events()->requestStart($request);
        events()->connectionAcquired($request, $this->createMock(Connection::class), 1);
        events()->requestHeaderStart($request, $this->createMock(Stream::class));
        events()->requestHeaderEnd($request, $this->createMock(Stream::class));
        events()->requestBodyStart($request, $this->createMock(Stream::class));
        events()->requestBodyEnd($request, $this->createMock(Stream::class));

        $body = '';
        $parser = new Http1Parser(
            $request,
            $this->createMock(Stream::class),
            function (string $data) use (&$body): Future {
                $body .= $data;
                return Future::complete();
            },
            new NullCancellation(),
            fn () => null,
        );

        $response = $parser->parse($data);
        while (!$parser->isComplete()) {
            $parser->parse();
        }

        return [$response, $body];
    }
}
