<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\Parser;
use Amp\Socket\SocketAddress;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testKeepAliveHeadResponseParse(): void
    {
        $request = "HTTP/1.1 200 OK\r\n\r\n";
        $parser = new Parser(new Request('/', 'HEAD'), new ConnectionInfo(new SocketAddress('127.0.0.1', 1234), new SocketAddress('127.0.0.1', 4321)));
        $response = $parser->parse($request);

        $this->assertEquals(200, $response->getStatus());
    }
}
