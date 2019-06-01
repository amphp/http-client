<?php /** @noinspection PhpUndefinedClassInspection */

namespace Amp\Http\Client;

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
        (new Request("http://127.0.0.1/"))->withProtocolVersions([$invalidVersion]);
    }

    public function testProtocolVersionsAcceptsNoEmptyArray(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Empty array of protocol versions provided, must not be empty.");
        (new Request("http://127.0.0.1/"))->withProtocolVersions([]);
    }

    public function testProtocolVersionsAcceptsValidInput(): void
    {
        $request = (new Request("http://127.0.0.1/"))->withProtocolVersions(["1.0"]);
        $this->assertSame(["1.0"], $request->getProtocolVersions());

        $request = (new Request("http://127.0.0.1/"))->withProtocolVersions(["1.0", "2.0"]);
        $this->assertSame(["1.0", "2.0"], $request->getProtocolVersions());
    }

    public function testProtocolVersionsReturnsSameInstanceWithoutChange(): void
    {
        $request1 = (new Request("http://127.0.0.1/"))->withProtocolVersions(["1.0"]);
        $request2 = $request1->withProtocolVersions(["1.0"]);
        $this->assertSame($request1, $request2);
    }

    public function testMethodReturnsSameInstanceWithoutChange(): void
    {
        $request1 = new Request("http://127.0.0.1/");
        $request2 = $request1->withMethod("GET");
        $this->assertSame($request1, $request2);
    }

    public function testHeader(): void
    {
        /** @var Request $request */
        $request = new Request("http://127.0.0.1/");
        $this->assertNull($request->getHeader("X-Foo"));
        $this->assertSame([], $request->getHeaderArray("X-Foo"));

        $request = $request->withHeader("x-FOO", "bar");
        $this->assertSame("bar", $request->getHeader("X-Foo"));
        $this->assertSame(["bar"], $request->getHeaderArray("X-Foo"));

        $request = $request->withAddedHeader("x-FOO", "baz");
        $this->assertSame("bar", $request->getHeader("X-Foo"));
        $this->assertSame(["bar", "baz"], $request->getHeaderArray("X-Foo"));

        $request = $request->withHeader("x-FOO", "bar");
        $this->assertSame("bar", $request->getHeader("X-Foo"));
        $this->assertSame(["bar"], $request->getHeaderArray("X-Foo"));

        $this->assertSame([
            "x-foo" => ["bar"],
        ], $request->getHeaders());

        $request = $request->withHeaders([
            "x-hello-world" => "xx",
            "x-goodbye" => ["yy", "zzz"],
        ]);

        $this->assertSame([
            "x-foo" => ["bar"],
            "x-hello-world" => ["xx"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getHeaders());

        // Empty array deletes
        $request = $request->withHeaders([
            "x-hello-world" => [],
        ]);

        $this->assertSame([
            "x-foo" => ["bar"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getHeaders());
    }

    public function provideBadAllHeaderInput(): array
    {
        return [
            [[
                "text" => [null],
            ]],
            [[
                "text" => null,
            ]],
        ];
    }

    /**
     * @dataProvider provideBadAllHeaderInput
     *
     * @param $input
     */
    public function testAllHeaders($input): void
    {
        $this->expectException(\TypeError::class);
        (new Request("http://127.0.0.1/"))->withHeaders($input);
    }

    public function testBody(): void
    {
        /** @var Request $request */
        $request = new Request("http://127.0.0.1/");
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request = $request->withBody(null);
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $request = $request->withBody("foobar");
        $this->assertInstanceOf(StringBody::class, $request->getBody());

        $this->expectException(\TypeError::class);
        $request->withBody(new \stdClass);
    }
}
