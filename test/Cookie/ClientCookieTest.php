<?php

namespace Amp\Test\Artax\Cookie;

use Amp\Artax\Client;
use Amp\Artax\Cookie\ArrayCookieJar;
use Amp\Artax\Cookie\Cookie;
use Amp\Artax\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;

class ClientCookieTest extends TestCase {
    /** @var Client */
    private $client;

    /** @var CookieJar */
    private $jar;

    public function setUp() {
        $this->jar = new ArrayCookieJar;
        $this->client = new Client($this->jar);
    }

    /** @dataProvider provideCookieDomainMatchData */
    public function testCookieAccepting(Cookie $cookie, string $requestDomain, bool $accept) {
        $method = (new \ReflectionClass($this->client))->getMethod("storeResponseCookie");
        $method->setAccessible(true);
        $method->invoke($this->client, $requestDomain, (string) $cookie);

        if ($accept) {
            $this->assertSame(1, \count($this->jar->getAll()));
        } else {
            $this->assertSame([], $this->jar->getAll());
        }
    }

    public function provideCookieDomainMatchData() {
        return [
            [new Cookie("foo", "bar", null, "/", ".foo.bar.example.com"), "foo.bar", false],
            [new Cookie("foo", "bar", null, "/", ".example.com"), "example.com", true],
            [new Cookie("foo", "bar", null, "/", ".example.com"), "www.example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "www.example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", "anotherexample.com"), "example.com", false],
            [new Cookie("foo", "bar", null, "/", "com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", ".com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", ""), "example.com", true],
        ];
    }
}