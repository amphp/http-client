<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Test\Body;

use Amp\Http\Client\Body\JsonBody;
use Amp\Http\Client\HttpException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class JsonBodyTest extends AsyncTestCase
{
    public function testSuccess(): \Generator
    {
        $body = new JsonBody(['foo' => 'bar']);
        $payload = '{"foo":"bar"}';

        $this->assertSame(['content-type' => 'application/json; charset=utf-8'], yield $body->getHeaders());
        $this->assertSame($payload, yield buffer($body->createBodyStream()));
        $this->assertSame(\strlen($payload), yield $body->getBodyLength());
    }

    public function testFailure(): void
    {
        $this->expectException(HttpException::class);

        new JsonBody([
            'foo' => \fopen('php://memory', 'rb'),
        ]);
    }
}
