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
    }

    public function testReason(): void
    {
        $response = $this->createResponse();
        $response->setReason('Hello');

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

        $response1 = (clone $response);
        $response1->setHeader('foo', 'bar');

        $response2 = (clone $response1);
        $response2->addHeader('foo', 'baz');

        $response3 = (clone $response2);
        $response3->removeHeader('fOo');

        $response4 = (clone $response);
        $response4->setHeaders(['foO' => ['bar', 'bax']]);

        $this->assertNull($response->getHeader('Foo'));
        $this->assertFalse($response->hasHeader('Foo'));
        $this->assertSame([], $response->getHeaderArray('Foo'));

        $this->assertSame('bar', $response1->getHeader('Foo'));
        $this->assertSame(['bar'], $response1->getHeaderArray('fOO'));
        $this->assertTrue($response1->hasHeader('fOO'));

        $this->assertSame('bar', $response2->getHeader('Foo'));
        $this->assertSame(['bar', 'baz'], $response2->getHeaderArray('fOO'));
        $this->assertTrue($response2->hasHeader('fOO'));

        $this->assertNull($response3->getHeader('Foo'));
        $this->assertFalse($response3->hasHeader('Foo'));
        $this->assertSame([], $response3->getHeaderArray('Foo'));

        $this->assertSame('bar', $response4->getHeader('Foo'));
        $this->assertSame(['bar', 'bax'], $response4->getHeaderArray('fOO'));
        $this->assertTrue($response4->hasHeader('fOO'));
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
