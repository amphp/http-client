<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Interceptor\FollowRedirects;
use Amp\Http\Status;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;

class FollowRedirectsTest extends AsyncTestCase
{
    /**
     * @dataProvider provideUris
     */
    public function testResolve(string $baseUri, string $toResolve, string $expectedResult): void
    {
        $redirect = new FollowRedirects(10);
        $request = new Request($baseUri);

        $stream1 = $this->createMock(Stream::class);
        $stream1->method('request')
            ->willReturn(new Response(
                '1.1',
                Status::MOVED_PERMANENTLY,
                Status::getReason(Status::MOVED_PERMANENTLY),
                ['location' => [$toResolve]],
                new ReadableBuffer(),
                $request
            ));

        $stream2 = $this->createMock(Stream::class);
        $stream2->method('request')
            ->willReturnCallback(function (Request $redirected) use ($request, $expectedResult): Response {
                $this->assertSame($expectedResult, (string) $redirected->getUri());
                return new Response(
                    '1.1',
                    Status::OK,
                    Status::getReason(Status::OK),
                    [],
                    new ReadableBuffer(),
                    $request
                );
            });

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getStream')
            ->willReturnOnConsecutiveCalls($stream1, $stream2);

        $client = new PooledHttpClient($pool);

        $redirect->request($request, new NullCancellation, $client);
    }

    public function provideUris(): array
    {
        return [
            ['http://localhost/1/2/a.php', 'http://google.com/', 'http://google.com/'],
            [
                'http://www.google.com/',
                '/level1/level2/test.php',
                'http://www.google.com/level1/level2/test.php',
            ],
            ['http://localhost/1/2/a.php', '../b.php', 'http://localhost/1/b.php'],
            ['http://localhost/1/2/a.php', '../../b.php', 'http://localhost/b.php'],
            ['http://localhost/', './', 'http://localhost/'],
            ['http://localhost/', './dir/', 'http://localhost/dir/'],
            ['http://localhost/', '././', 'http://localhost/'],
            ['http://localhost/', '././dir/', 'http://localhost/dir/'],
            ['http://localhost/', '#frag', 'http://localhost/#frag'],
            ['http://localhost/', '?query', 'http://localhost/?query'],
            [
                'http://localhost/',
                'http://www.google.com/%22-%3Eresolve%28%22..%2F..%2F%22%29',
                'http://www.google.com/%22-%3Eresolve%28%22..%2F..%2F%22%29',
            ],
            ["http://a/b/c/d;p?q", "g", "http://a/b/c/g"],
            ["http://a/b/c/d;p?q", "./g", "http://a/b/c/g"],
            ["http://a/b/c/d;p?q", "g/", "http://a/b/c/g/"],
            ["http://a/b/c/d;p?q", "/g", "http://a/g"],
            ["http://a/b/c/d;p?q", "//g", "http://g"],
            ["http://a/b/c/d;p?q", "?y", "http://a/b/c/d;p?y"],
            ["http://a/b/c/d;p?q", "g?y", "http://a/b/c/g?y"],
            ["http://a/b/c/d;p?q", "#s", "http://a/b/c/d;p#s"],
            ["http://a/b/c/d;p?q", "g#s", "http://a/b/c/g#s"],
            ["http://a/b/c/d;p?q", "g?y#s", "http://a/b/c/g?y#s"],
            ["http://a/b/c/d;p?q", ";x", "http://a/b/c/;x"],
            ["http://a/b/c/d;p?q", "g;x", "http://a/b/c/g;x"],
            ["http://a/b/c/d;p?q", "g;x?y#s", "http://a/b/c/g;x?y#s"],
            ["http://a/b/c/d;p?q", "", "http://a/b/c/d;p?q"],
            ["http://a/b/c/d;p?q", ".", "http://a/b/c/"],
            ["http://a/b/c/d;p?q", "./", "http://a/b/c/"],
            ["http://a/b/c/d;p?q", "..", "http://a/b/"],
            ["http://a/b/c/d;p?q", "../", "http://a/b/"],
            ["http://a/b/c/d;p?q", "../g", "http://a/b/g"],
            ["http://a/b/c/d;p?q", "../..", "http://a/"],
            ["http://a/b/c/d;p?q", "../../", "http://a/"],
            ["http://a/b/c/d;p?q", "../../g", "http://a/g"],
            ["https://telegram.me/", "//telegram.org/", "https://telegram.org/"],
        ];
    }
}
