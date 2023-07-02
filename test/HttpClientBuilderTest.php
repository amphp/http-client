<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\PHPUnit\AsyncTestCase;

class HttpClientBuilderTest extends AsyncTestCase
{
    public function testUserInfoDeprecation(): void
    {
        $client = HttpClientBuilder::buildDefault();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('The user information (username:password) component of URIs has been deprecated');

        $client->request(new Request('https://foobar@google.com/'));
    }

    public function testUserInfoDeprecationAllow(): void
    {
        $client = (new HttpClientBuilder)->allowDeprecatedUriUserInfo()->build();

        $response = $client->request(new Request('http://foobar@amphp.org/amp'));

        $this->assertTrue(true); // no exception
        $this->assertNotNull($response->getPreviousResponse());
        $this->assertNull($response->getPreviousResponse()->getPreviousResponse());
        $this->assertSame('https://amphp.org/amp', (string) $response->getRequest()->getUri());
        $this->assertSame('http://amphp.org/amp', $response->getRequest()->getHeader('referer'));
        $this->assertSame('http://foobar@amphp.org/amp', (string) $response->getPreviousResponse()->getRequest()->getUri());
    }
}
