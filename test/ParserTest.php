<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testKeepAliveHeadResponseParse(): void
    {
        $request = "HTTP/1.1 200 OK\n\n";
        $msgParser = new Parser(null);
        $msgParser->enqueueResponseMethodMatch('HEAD');
        $parsedResponseArr = $msgParser->parse($request);

        $this->assertEquals(200, $parsedResponseArr['status']);
    }
}
