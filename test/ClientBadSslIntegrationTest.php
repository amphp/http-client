<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\PHPUnit\AsyncTestCase;

final class ClientBadSslIntegrationTest extends AsyncTestCase
{
    private HttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = (new HttpClientBuilder)->retry(0)->build();
    }

    public function testSelfSignedCertificate(): void
    {
        $request = new Request('https://self-signed.badssl.com/');

        $this->expectException(TlsException::class);
        $this->expectExceptionMessageMatches("/^Connection to 'self-signed.badssl.com:443' @ '.+' closed during TLS handshake: certificate verify failed$/");

        $this->client->request($request);
    }

    public function testWrongHostCertificate(): void
    {
        $request = new Request('https://wrong.host.badssl.com/');

        $this->expectException(TlsException::class);
        $this->expectExceptionMessageMatches("/^Connection to 'wrong.host.badssl.com:443' @ '.+' closed during TLS handshake: Peer certificate CN=`\*.badssl.com' did not match expected CN=`wrong.host.badssl.com'$/");

        $this->client->request($request);
    }

    public function testDhKeyTooSmall(): void
    {
        $request = new Request('https://dh512.badssl.com/');

        $this->expectException(TlsException::class);
        $this->expectExceptionMessageMatches("/^Connection to 'dh512.badssl.com:443' @ '.+' closed during TLS handshake: dh key too small$/");

        $this->client->request($request);
    }
}
