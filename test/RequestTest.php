<?php

namespace Amp\Artax\Test;

use Amp\Artax\AggregateBody;
use Amp\Artax\Request;
use Amp\Artax\StringBody;
use Amp\PHPUnit\TestCase;

class RequestTest extends TestCase {
    public function provideInvalidProtocolVersions() {
        return [
            ["HTTP/1.0"],
            ["HTTP/1.1"],
            ["HTTP 1.0"],
            ["3.0"],
            ["1.2"],
        ];
    }

    /** @dataProvider provideInvalidProtocolVersions */
    public function testProtocolVersionsAcceptsNoInvalidValues($invalidVersion) {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Invalid HTTP protocol version");
        (new Request("http://127.0.0.1/"))->withProtocolVersions([$invalidVersion]);
    }

    public function testProtocolVersionsAcceptsNoEmptyArray() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Empty array of protocol versions provided, must not be empty.");
        (new Request("http://127.0.0.1/"))->withProtocolVersions([]);
    }

    public function testProtocolVersionsAcceptsValidInput() {
        $request = (new Request("http://127.0.0.1/"))->withProtocolVersions(["1.0"]);
        $this->assertSame(["1.0"], $request->getProtocolVersions());

        $request = (new Request("http://127.0.0.1/"))->withProtocolVersions(["1.0", "2.0"]);
        $this->assertSame(["1.0", "2.0"], $request->getProtocolVersions());
    }

    public function testProtocolVersionsReturnsSameInstanceWithoutChange() {
        $request1 = (new Request("http://127.0.0.1/"))->withProtocolVersions(["1.0"]);
        $request2 = $request1->withProtocolVersions(["1.0"]);
        $this->assertSame($request1, $request2);
    }

    public function testMethodReturnsSameInstanceWithoutChange() {
        $request1 = new Request("http://127.0.0.1/");
        $request2 = $request1->withMethod("GET");
        $this->assertSame($request1, $request2);
    }

    public function testHeader() {
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
            "x-foo" => ["bar"]
        ], $request->getAllHeaders());

        $request = $request->withAllHeaders([
            "x-hello-world" => "xx",
            "x-goodbye" => ["yy", "zzz"],
        ]);

        $this->assertSame([
            "x-foo" => ["bar"],
            "x-hello-world" => ["xx"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getAllHeaders());

        // Empty array deletes
        $request = $request->withAllHeaders([
            "x-hello-world" => [],
        ]);

        $this->assertSame([
            "x-foo" => ["bar"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getAllHeaders());

        $this->assertSame([
            "x-FOO" => ["bar"],
            "x-goodbye" => ["yy", "zzz"],
        ], $request->getAllHeaders(true));
    }

    public function provideBadAllHeaderInput() {
        return [
            [[
                "text" => [null],
            ]],
            [[
                "text" => null,
            ]]
        ];
    }

    /** @dataProvider provideBadAllHeaderInput */
    public function testAllHeaders($input) {
        $this->expectException(\TypeError::class);
        (new Request("http://127.0.0.1/"))->withAllHeaders($input);
    }

    public function testBody() {
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
