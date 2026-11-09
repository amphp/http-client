<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Interceptor\FollowRedirects;
use Amp\Http\HttpStatus;
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
                HttpStatus::MOVED_PERMANENTLY,
                HttpStatus::getReason(HttpStatus::MOVED_PERMANENTLY),
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
                    HttpStatus::OK,
                    HttpStatus::getReason(HttpStatus::OK),
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

    /**
     * @dataProvider provideCredentialRedirects
     */
    public function testCredentialHeadersOnRedirect(string $from, string $location, bool $expectCredentials): void
    {
        $redirect = new FollowRedirects(10);
        $request = new Request($from);
        $request->setHeader('authorization', 'Bearer secret-token');
        $request->setHeader('cookie', 'session=secret-session');

        $stream1 = $this->createMock(Stream::class);
        $stream1->method('request')
            ->willReturn(new Response(
                '1.1',
                HttpStatus::FOUND,
                HttpStatus::getReason(HttpStatus::FOUND),
                ['location' => [$location]],
                new ReadableBuffer(),
                $request
            ));

        $redirected = null;
        $stream2 = $this->createMock(Stream::class);
        $stream2->method('request')
            ->willReturnCallback(function (Request $request) use (&$redirected): Response {
                $redirected = $request;
                return new Response(
                    '1.1',
                    HttpStatus::OK,
                    HttpStatus::getReason(HttpStatus::OK),
                    [],
                    new ReadableBuffer(),
                    $request
                );
            });

        $pool = $this->createMock(ConnectionPool::class);
        $pool->method('getStream')
            ->willReturnOnConsecutiveCalls($stream1, $stream2);

        $redirect->request($request, new NullCancellation, new PooledHttpClient($pool));

        self::assertInstanceOf(Request::class, $redirected);
        self::assertSame($expectCredentials, $redirected->hasHeader('authorization'));
        self::assertSame($expectCredentials, $redirected->hasHeader('cookie'));
    }

    public function provideCredentialRedirects(): array
    {
        return [
            'same origin' => ['https://example.com/a', '/b', true],
            'other host' => ['https://example.com/a', 'https://example.org/b', false],
            'https to http, same host' => ['https://example.com/a', 'http://example.com/b', false],
            'http to https, same host' => ['http://example.com/a', 'https://example.com/b', false],
        ];
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
