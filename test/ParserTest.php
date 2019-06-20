<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Http1Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testKeepAliveHeadResponseParse(): void
    {
        $request = "HTTP/1.1 200 OK\r\n\r\n";
        $parser = new Http1Parser(new Request('/', 'HEAD'), $this->createMock(Connection::class));
        $response = $parser->parse($request);

        $this->assertEquals(200, $response->getStatus());
    }
}
