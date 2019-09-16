<?php /** @noinspection PhpUndefinedClassInspection */

namespace Amp\Http\Client\Test;

use Amp\Http\Client\Body\StringBody;
use Amp\Http\Client\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
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

    public function testBody(): void
    {
        /** @var Request $request */
        $request = new Request("http://127.0.0.1/");
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request->setBody(null);
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request->setBody("foobar");
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $this->expectException(\TypeError::class);
        $request->setBody(new \stdClass);
    }
}
