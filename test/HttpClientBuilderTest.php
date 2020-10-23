<?php

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
        $this->markTestSkipped('causes segfaults');

        $client = (new HttpClientBuilder)->allowDeprecatedUriUserInfo()->build();

        $response = $client->request(new Request('http://foobar@amphp.org/amp'));

        $this->assertTrue(true); // no exception
        $this->assertNotNull($response->getPreviousResponse());
        $this->assertNotNull($response->getPreviousResponse()->getPreviousResponse());
        $this->assertNotNull($response->getPreviousResponse()->getPreviousResponse()->getPreviousResponse());
        $this->assertNull($response->getPreviousResponse()->getPreviousResponse()->getPreviousResponse()->getPreviousResponse());
        $this->assertSame('https://amphp.org/amp/', (string) $response->getRequest()->getUri());
        $this->assertSame('http://amphp.org/amp', $response->getPreviousResponse()->getPreviousResponse()->getRequest()->getHeader('referer'));
    }
}
