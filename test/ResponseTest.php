<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use Amp\PHPUnit\AsyncTestCase;

class ResponseTest extends AsyncTestCase
{
    public function provideInvalidProtocolVersions(): array
    {
        return [
            ["HTTP/1.0"],
            ["HTTP/1.1"],
            ["HTTP 1.0"],
            ["3.0"],
            ["1.2"],
        ];
    }

    /**
     * @dataProvider provideInvalidProtocolVersions
     *
     * @param $invalidVersion
     */
    public function testProtocolVersionsAcceptsNoInvalidValues($invalidVersion): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Invalid HTTP protocol version");
        (new Response($invalidVersion, 200, null, [], new InMemoryStream, new Request('https://localhost/')));
    }

    public function testProtocolVersion(): void
    {
        $response = $this->createResponse();
        $response->setProtocolVersion('2');

        $this->assertSame('2', $response->getProtocolVersion());
    }

    public function testPseudoSetHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Response('2', 200, null, [], new InMemoryStream, new Request('https://google.com/')))->setHeader(
            ':foobar',
            'foobar'
        );
    }

    public function testEmptySetHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Response('2', 200, null, [], new InMemoryStream, new Request('https://google.com/')))->setHeader(
            '',
            'foobar'
        );
    }

    public function testPseudoAddHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Response('2', 200, null, [], new InMemoryStream, new Request('https://google.com/')))->addHeader(
            ':foobar',
            'foobar'
        );
    }

    public function testEmptyAddHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Response('2', 200, null, [], new InMemoryStream, new Request('https://google.com/')))->addHeader(
            '',
            'foobar'
        );
    }

    public function testBody(): \Generator
    {
        /** @var Response $response */
        $response = new Response('2', 200, null, [], new InMemoryStream, new Request('https://google.com/'));

        $response->setBody(null);
        $this->assertSame('', yield $response->getBody()->buffer());

        $response->setBody("foobar");
        $this->assertSame('foobar', yield $response->getBody()->buffer());

        $response->setBody($response->getBody());
        $this->assertSame('foobar', yield $response->getBody()->buffer());

        $response->setBody(new InMemoryStream('foobar2'));
        $this->assertSame('foobar2', yield $response->getBody()->buffer());

        $response->setBody(143);
        $this->assertSame('143', yield $response->getBody()->buffer());

        $this->expectException(\TypeError::class);
        $response->setBody(new \stdClass);
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
