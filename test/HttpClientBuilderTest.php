<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Http\Client\Support\LocalHttpBin;
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

        $httpBin = LocalHttpBin::startInsecureServer();
        $authority = $httpBin->getServerAuthority();

        $response = $client->request(new Request("http://foobar@{$authority}/redirect/status/200"));
        $httpBin->stopServer();

        $this->assertTrue(true); // no exception
        $this->assertNotNull($response->getPreviousResponse());
        $this->assertNull($response->getPreviousResponse()->getPreviousResponse());
        $this->assertSame("http://{$authority}/status/200", (string) $response->getRequest()->getUri());
        $this->assertSame("http://{$authority}/redirect/status/200", $response->getRequest()->getHeader('referer'));
        $this->assertSame("http://foobar@{$authority}/redirect/status/200", (string) $response->getPreviousResponse()->getRequest()->getUri());
    }
}
