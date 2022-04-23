<?php

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableBuffer;
use Amp\Future;
use Amp\Http\Client\Body\StringBody;
use Amp\PHPUnit\AsyncTestCase;

class RequestTest extends AsyncTestCase
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
        (new Request("http://127.0.0.1/"))->setProtocolVersions([$invalidVersion]);
    }

    public function testProtocolVersionsAcceptsNoEmptyArray(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Empty array of protocol versions provided, must not be empty.");
        (new Request("http://127.0.0.1/"))->setProtocolVersions([]);
    }

    public function testProtocolVersionsAcceptsValidInput(): void
    {
        $request = new Request("http://127.0.0.1/");
        $request->setProtocolVersions(["1.0"]);
        $this->assertSame(["1.0"], $request->getProtocolVersions());

        $request = new Request("http://127.0.0.1/");
        $request->setProtocolVersions(["1.0", "2"]);
        $this->assertSame(["1.0", "2"], $request->getProtocolVersions());
    }

    public function testHeader(): void
    {
        /** @var Request $request */
        $request = new Request("http://127.0.0.1/");
        $this->assertNull($request->getHeader("X-Foo"));
        $this->assertSame([], $request->getHeaderArray("X-Foo"));

        $request->setHeader("x-FOO", "bar");
        $this->assertSame("bar", $request->getHeader("X-Foo"));
        $this->assertSame(["bar"], $request->getHeaderArray("X-Foo"));

        $request->addHeader("x-FOO", "baz");
        $this->assertSame("bar", $request->getHeader("X-Foo"));
        $this->assertSame(["bar", "baz"], $request->getHeaderArray("X-Foo"));

        $request->setHeader("x-FOO", "bar");
        $this->assertSame("bar", $request->getHeader("X-Foo"));
        $this->assertSame(["bar"], $request->getHeaderArray("X-Foo"));

        $this->assertSame([
            "x-foo" => ["bar"],
        ], $request->getHeaders());

        $request->setHeaders([
            "x-hello-world" => "xx",
            "x-goodbye" => ["yy", "zzz"],
        ]);

        $this->assertSame([
            "x-foo" => ["bar"],
            "x-hello-world" => ["xx"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getHeaders());

        // Empty array deletes
        $request->setHeaders([
            "x-hello-world" => [],
        ]);

        $this->assertSame([
            "x-foo" => ["bar"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getHeaders());
    }

    public function testPseudoSetHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Request('https://google.com/'))->setHeader(':foobar', 'foobar');
    }

    public function testEmptySetHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Request('https://google.com/'))->setHeader('', 'foobar');
    }

    public function testPseudoAddHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Request('https://google.com/'))->addHeader(':foobar', 'foobar');
    }

    public function testEmptyAddHeader(): void
    {
        $this->expectExceptionMessage('Header name cannot be empty or start with a colon (:)');

        (new Request('https://google.com/'))->addHeader('', 'foobar');
    }

    public function testBody(): void
    {
        $request = new Request("http://127.0.0.1/");
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request->setBody('');
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request->setBody("foobar");
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request->setBody(143);
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $this->expectException(\TypeError::class);

        /** @noinspection PhpParamsInspection */
        $request->setBody(new \stdClass);
    }

    public function testPushHandler(): void
    {
        $request = new Request('https://amphp.org/');
        $invocationCount = 0;
        $pushHandler = static function () use (&$invocationCount) {
            $invocationCount++;
        };

        $this->assertNull($request->getPushHandler());

        $request->setPushHandler($pushHandler);

        $this->assertSame($pushHandler, $request->getPushHandler());

        $request->getPushHandler()();

        $this->assertSame(1, $invocationCount);
    }

    public function testPushHandlerInterceptNull(): void
    {
        $request = new Request('https://amphp.org/');
        $invocationCount = 0;
        $pushHandler = static function () use (&$invocationCount) {
            $invocationCount++;
        };

        $this->assertNull($request->getPushHandler());

        $request->interceptPush($pushHandler);

        $this->assertNull($request->getPushHandler());
    }

    public function testPushHandlerInterceptNullReturn(): void
    {
        $request = new Request('https://amphp.org/');
        $invocationCount = 0;
        $responseFuture = null;
        $pushHandler = static function (Request $request, Future $response) use (
            &$invocationCount,
            &$responseFuture
        ) {
            $invocationCount++;
            $responseFuture = $response;
        };

        $request->setPushHandler($pushHandler);
        $request->interceptPush(static function (Request $request, Response $response): Response {
            $response->setStatus(512);
            return $response;
        });

        $request->getPushHandler()(
            new Request('https://amphp.org/'),
            Future::complete(new Response('2', 200, null, [], new ReadableBuffer(), $request))
        );

        $response = $responseFuture->await();

        $this->assertSame(512, $response->getStatus());
    }

    public function testRemoveNonexistentAttribute(): void
    {
        $request = new Request('https://amphp.org/');

        $this->expectException(MissingAttributeError::class);
        $this->expectExceptionMessage('The requested attribute \'foobar\' does not exist');
        $request->removeAttribute('foobar');
    }

    public function testPushHandlerInterceptNewReturn(): void
    {
        $request = new Request('https://amphp.org/');
        $invocationCount = 0;
        $responseFuture = null;
        $pushHandler = static function (Request $request, Future $response) use (
            &$invocationCount,
            &$responseFuture
        ) {
            $invocationCount++;
            $responseFuture = $response;
        };

        $request->setPushHandler($pushHandler);
        $request->interceptPush(static function (Request $request, Response $response) {
            return new Response('2', 523, null, [], new ReadableBuffer(), $response->getRequest());
        });

        $request->getPushHandler()(
            new Request('https://amphp.org/'),
            Future::complete(new Response('2', 200, null, [], new ReadableBuffer(), $request))
        );

        $response = $responseFuture->await();

        $this->assertSame(523, $response->getStatus());
    }

    public function testHeaderSizeLimit(): void
    {
        $request = new Request('https://amphp.org/');

        $this->assertSame(Request::DEFAULT_HEADER_SIZE_LIMIT, $request->getHeaderSizeLimit());

        $request->setHeaderSizeLimit(100);

        $this->assertSame(100, $request->getHeaderSizeLimit());
    }

    public function testBodySizeLimit(): void
    {
        $request = new Request('https://amphp.org/');

        $this->assertSame(Request::DEFAULT_BODY_SIZE_LIMIT, $request->getBodySizeLimit());

        $request->setBodySizeLimit(100);

        $this->assertSame(100, $request->getBodySizeLimit());
    }

    public function testIdempotent(): void
    {
        $this->assertTrue((new Request('https://localhost/', 'GET'))->isIdempotent());
        $this->assertTrue((new Request('https://localhost/', 'HEAD'))->isIdempotent());
        $this->assertTrue((new Request('https://localhost/', 'PUT'))->isIdempotent());
        $this->assertTrue((new Request('https://localhost/', 'DELETE'))->isIdempotent());
        $this->assertFalse((new Request('https://localhost/', 'CONNECT'))->isIdempotent());
        $this->assertFalse((new Request('https://localhost/', 'POST'))->isIdempotent());
        $this->assertFalse((new Request('https://localhost/', 'PATCH'))->isIdempotent());
    }

    public function testAttributes(): void
    {
        $request = new Request("http://127.0.0.1/");
        $request->setAttribute('foo', 'bar');

        $this->assertSame('bar', $request->getAttribute('foo'));
        $this->assertTrue($request->hasAttribute('foo'));
        $this->assertSame(['foo' => 'bar'], $request->getAttributes());

        $request->removeAttribute('foo');

        $this->assertFalse($request->hasAttribute('foo'));

        $this->expectException(MissingAttributeError::class);

        $request->getAttribute('foo');
    }

    public function testRemoveAttributes(): void
    {
        $request = new Request("http://127.0.0.1/");
        $request->setAttribute('foo', 'bar');
        $request->setAttribute('a', 'b');

        $request->removeAttributes();

        $this->assertSame([], $request->getAttributes());
    }
}
