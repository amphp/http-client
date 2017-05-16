<?php

namespace Amp\Test\Artax\Cookie;

use Amp\Artax\Cookie\Cookie;
use Amp\Artax\Cookie\CookieFormatException;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase {
    public function testGetters() {
        $cookie = new Cookie("name", "value", null, null, "example.com", true, false);

        $this->assertSame("name", $cookie->getName());
        $this->assertSame("value", $cookie->getValue());
        $this->assertNull(null, $cookie->getExpirationTime());
        $this->assertSame("/", $cookie->getPath());
        $this->assertSame("example.com", $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testSetters() {
        $cookie = new Cookie("name", "value", null, null, "example.com", true, false);

        $new = $cookie->withName("name2");
        $this->assertSame("name", $cookie->getName());
        $this->assertSame("name2", $new->getName());

        $new = $cookie->withValue("value2");
        $this->assertSame("value", $cookie->getValue());
        $this->assertSame("value2", $new->getValue());

        $new = $cookie->withExpirationTime(42);
        $this->assertNull($cookie->getExpirationTime());
        $this->assertSame(42, $new->getExpirationTime());

        $new = $cookie->withPath("/foobar");
        $this->assertSame("/", $cookie->getPath());
        $this->assertSame("/foobar", $new->getPath());

        $new = $cookie->withDomain("example.net");
        $this->assertSame("example.com", $cookie->getDomain());
        $this->assertSame("example.net", $new->getDomain());

        $new = $cookie->withSecure(false);
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($new->isSecure());

        $new = $cookie->withHttpOnly(true);
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertTrue($new->isHttpOnly());
    }

    public function testEmptyCookieStringThrows() {
        $this->expectException(CookieFormatException::class);
        $this->expectExceptionMessage("Empty cookie string");

        Cookie::fromString("");
    }

    public function testMissingEqualsSignThrows() {
        $this->expectException(CookieFormatException::class);
        $this->expectExceptionMessage("Missing '=' to separate name and value");

        Cookie::fromString("name");
    }

    public function testEmptyNameThrows() {
        $this->expectException(CookieFormatException::class);
        $this->expectExceptionMessage("Empty name");

        Cookie::fromString("=value");
    }

    public function testValidCookieParse() {
        $cookie = Cookie::fromString("cookie_name2=cookie_value2; expires=Sun, 16 Jul 3567 06:23:41 GMT");

        $this->assertSame("cookie_name2", $cookie->getName());
        $this->assertSame("cookie_value2", $cookie->getValue());
        $this->assertNotNull($cookie->getExpirationTime());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertFalse($cookie->isSecure());
        $this->assertSame("/", $cookie->getPath());
        $this->assertSame("", $cookie->getDomain());
        $this->assertFalse($cookie->isExpired());
    }

    public function testValidCookieParseAndStringify() {
        $cookie1 = Cookie::fromString("cookie_name2=cookie_value2; expires=Sun, 16 Jul 3567 06:23:41 GMT; secure; hTTPonly; unknown; domain=example.com");
        $cookie2 = Cookie::fromString((string) $cookie1);

        foreach ([$cookie1, $cookie2] as $cookie) {
            $this->assertSame("cookie_name2", $cookie->getName());
            $this->assertSame("cookie_value2", $cookie->getValue());
            $this->assertNotNull($cookie->getExpirationTime());
            $this->assertTrue($cookie->isHttpOnly());
            $this->assertTrue($cookie->isSecure());
            $this->assertSame("/", $cookie->getPath());
            $this->assertSame("example.com", $cookie->getDomain());
            $this->assertFalse($cookie->isExpired());
        }
    }

    public function testMaxAge() {
        $cookie = Cookie::fromString("cookie_name2=cookie_value2; max-age=1");
        $this->assertFalse($cookie->isExpired());
        // TODO: Don't sleep here, test differently
        sleep(2);
        $this->assertTrue($cookie->isExpired());
    }

    public function testInvalidDate() {
        $this->expectException(CookieFormatException::class);

        Cookie::fromString("cookie_name2=cookie_value2; expires=1");
    }
}
