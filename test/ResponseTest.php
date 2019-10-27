<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testProtocolVersion(): void
    {
        $response = $this->createResponse();
        $response->setProtocolVersion('2');

        $this->assertSame('2', $response->getProtocolVersion());
    }

    public function testStatus(): void
    {
        $response = $this->createResponse();
        $response->setStatus(400);

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('Bad Request', $response->getReason());
    }

    public function testReason(): void
    {
        $response = $this->createResponse();
        $response->setStatus(400, 'Hello');

        $this->assertSame(400, $response->getStatus());
        $this->assertSame('Hello', $response->getReason());
    }

    public function testRequest(): void
    {
        $response = $this->createResponse();
        $response->setRequest(new Request('https://amphp.org/'));

        $this->assertSame('https://amphp.org/', (string) $response->getRequest()->getUri());
    }

    public function testPreviousResponse(): void
    {
        $response = $this->createResponse();
        $response->setPreviousResponse($response);

        $this->assertSame($response, $response->getPreviousResponse());
    }

    public function testHeader(): void
    {
        $response = $this->createResponse();

        $this->assertNull($response->getHeader('Foo'));
        $this->assertFalse($response->hasHeader('Foo'));
        $this->assertSame([], $response->getHeaderArray('Foo'));

        $response = $this->createResponse();
        $response->setHeader('foo', 'bar');

        $this->assertSame('bar', $response->getHeader('Foo'));
        $this->assertSame(['bar'], $response->getHeaderArray('fOO'));
        $this->assertTrue($response->hasHeader('fOO'));

        $response = $this->createResponse();
        $response->setHeader('foo', 'bar');
        $response->addHeader('foo', 'baz');

        $this->assertSame('bar', $response->getHeader('Foo'));
        $this->assertSame(['bar', 'baz'], $response->getHeaderArray('fOO'));
        $this->assertTrue($response->hasHeader('fOO'));

        $response->removeHeader('fOo');

        $this->assertNull($response->getHeader('Foo'));
        $this->assertFalse($response->hasHeader('Foo'));
        $this->assertSame([], $response->getHeaderArray('Foo'));

        $response = $this->createResponse();
        $response->setHeaders(['foO' => ['bar', 'bax']]);

        $this->assertSame('bar', $response->getHeader('Foo'));
        $this->assertSame(['bar', 'bax'], $response->getHeaderArray('fOO'));
        $this->assertTrue($response->hasHeader('fOO'));
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
