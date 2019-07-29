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

    public function testRequest(): void
    {
        $response = $this->createResponse();
        $clone = $response->withRequest(new Request('https://amphp.org/'));

        $this->assertSame('https://example.com/', (string) $response->getRequest()->getUri());
        $this->assertSame('https://amphp.org/', (string) $clone->getRequest()->getUri());
    }

    public function testPreviousResponse(): void
    {
        $response = $this->createResponse();
        $clone = $response->withPreviousResponse($response);

        $this->assertNull($response->getPreviousResponse());
        $this->assertSame($response, $clone->getPreviousResponse());
    }

    public function testHeader(): void
    {
        $response = $this->createResponse();
        $clone1 = $response->withHeader('foo', 'bar');
        $clone2 = $clone1->withAddedHeader('foo', 'baz');
        $clone3 = $clone2->withoutHeader('fOo');
        $clone4 = $response->withHeaders(['foO' => ['bar', 'bax']]);

        $this->assertNull($response->getHeader('Foo'));
        $this->assertFalse($response->hasHeader('Foo'));
        $this->assertSame([], $response->getHeaderArray('Foo'));

        $this->assertSame('bar', $clone1->getHeader('Foo'));
        $this->assertSame(['bar'], $clone1->getHeaderArray('fOO'));
        $this->assertTrue($clone1->hasHeader('fOO'));

        $this->assertSame('bar', $clone2->getHeader('Foo'));
        $this->assertSame(['bar', 'baz'], $clone2->getHeaderArray('fOO'));
        $this->assertTrue($clone2->hasHeader('fOO'));

        $this->assertNull($clone3->getHeader('Foo'));
        $this->assertFalse($clone3->hasHeader('Foo'));
        $this->assertSame([], $clone3->getHeaderArray('Foo'));

        $this->assertSame('bar', $clone4->getHeader('Foo'));
        $this->assertSame(['bar', 'bax'], $clone4->getHeaderArray('fOO'));
        $this->assertTrue($clone4->hasHeader('fOO'));
    }

    private function createResponse(): Response
    {
        return new Response(
            '1.1',
            200,
            'OK',
            [],
            new InMemoryStream,
            new Request('https://example.com/')
        );
    }
}
