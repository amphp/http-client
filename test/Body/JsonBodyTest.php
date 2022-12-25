<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Body;

use Amp\Http\Client\HttpException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class JsonBodyTest extends AsyncTestCase
{
    public function testSuccess(): void
    {
        $body = new JsonBody(['foo' => 'bar']);
        $payload = '{"foo":"bar"}';

        $this->assertSame(['content-type' => 'application/json; charset=utf-8'], $body->getHeaders());
        $this->assertSame($payload, buffer($body->createBodyStream()));
        $this->assertSame(\strlen($payload), $body->getBodyLength());
    }

    public function testFailure(): void
    {
        $this->expectException(HttpException::class);

        new JsonBody([
            'foo' => \fopen('php://memory', 'rb'),
        ]);
    }
}
