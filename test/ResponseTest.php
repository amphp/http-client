<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use Amp\Socket\SocketAddress;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testProtocolVersion(): void
    {
        $response = $this->createResponse();
        $clone = $response->withProtocolVersion('2.0');

        $this->assertSame('1.1', $response->getProtocolVersion());
        $this->assertSame('2.0', $clone->getProtocolVersion());
    }

    public function testStatus(): void
    {
        $response = $this->createResponse();
        $clone = $response->withStatus(400);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame(400, $clone->getStatus());
    }

    public function testReason(): void
    {
        $response = $this->createResponse();
        $clone = $response->withReason('Hello');

        $this->assertSame('OK', $response->getReason());
        $this->assertSame('Hello', $clone->getReason());
    }

    private function createResponse(): Response
    {
        return new Response(
            '1.1',
            200,
            'OK',
            [],
            new InMemoryStream,
            new Request('https://example.com/'),
            new ConnectionInfo(new SocketAddress(''), new SocketAddress(''))
        );
    }
}
