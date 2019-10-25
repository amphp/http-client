<?php

namespace Amp\Http\Client\Test;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\PHPUnit\AsyncTestCase;

class HttpClientBuilderTest extends AsyncTestCase
{
    public function testUserInfoDeprecation(): \Generator
    {
        $client = HttpClientBuilder::buildDefault();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('The user information (username:password) component of URIs has been deprecated');

        yield $client->request(new Request('https://foobar@google.com/'));
    }

    public function testUserInfoDeprecationAllow(): \Generator
    {
        $client = (new HttpClientBuilder)->allowDeprecatedUriUserInfo()->build();

        /** @var Response $response */
        $response = yield $client->request(new Request('http://foobar@amphp.org/amp'));

        $this->assertTrue(true); // no exception
        $this->assertNotNull($response->getPreviousResponse());
        $this->assertNotNull($response->getPreviousResponse()->getPreviousResponse());
        $this->assertNotNull($response->getPreviousResponse()->getPreviousResponse()->getPreviousResponse());
        $this->assertNull($response->getPreviousResponse()->getPreviousResponse()->getPreviousResponse()->getPreviousResponse());
        $this->assertSame('https://amphp.org/amp/', (string) $response->getRequest()->getUri());
        $this->assertSame('http://amphp.org/amp', (string) $response->getPreviousResponse()->getPreviousResponse()->getRequest()->getHeader('referer'));
    }
}
