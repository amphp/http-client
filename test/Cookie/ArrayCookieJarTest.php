<?php

namespace Amp\Test\Artax\Cookie;

use Amp\Http\Client\Cookie\ArrayCookieJar;
use Amp\Http\Client\Cookie\Cookie;
use Amp\Http\Client\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;

class ArrayCookieJarTest extends TestCase
{
    /** @var CookieJar */
    private $jar;

    public function setUp(): void
    {
        $this->jar = new ArrayCookieJar;
    }

    /** @dataProvider provideCookieDomainMatchData */
    public function testCookieDomainMatching(Cookie $cookie, $domain, $returned)
    {
        $this->jar->store($cookie);

        if ($returned) {
            $this->assertSame([$cookie], $this->jar->get($domain));
        } else {
            $this->assertSame([], $this->jar->get($domain));
        }
    }

    public function provideCookieDomainMatchData()
    {
        // See http://stackoverflow.com/a/1063760/2373138 for cases
        return [
            [new Cookie("foo", "bar", null, "/", ".foo.bar.example.com"), "foo.bar", false], /* previous security issue */
            [new Cookie("foo", "bar", null, "/", ".example.com"), "example.com", true],
            [new Cookie("foo", "bar", null, "/", ".example.com"), "www.example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "www.example.com", false],
            [new Cookie("foo", "bar", null, "/", "example.com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", "anotherexample.com"), "example.com", false],
        ];
    }
}
