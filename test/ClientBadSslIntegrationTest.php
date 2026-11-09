<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Support\LocalHttpBin;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocket;
use Revolt\EventLoop;
use function Amp\delay;
use function Amp\Socket\listen;

final class ClientBadSslIntegrationTest extends AsyncTestCase
{
    public function testSelfSignedCertificate(): void
    {
        $httpBin = LocalHttpBin::startSecureServer();
        $client = (new HttpClientBuilder)->retry(0)->build();

        $this->expectException(TlsException::class);
        $this->expectExceptionMessageMatches("/^Connection to '127.0.0.1:\d+' @ '.+' closed during TLS handshake: certificate verify failed$/");

        try {
            $client->request(new Request($httpBin->getServerUri()));
        } finally {
            $httpBin->stopServer();
        }
    }

    public function testWrongHostCertificate(): void
    {
        $httpBin = LocalHttpBin::startSecureServer();
        $tlsContext = (new ClientTlsContext(''))->withoutPeerVerification()->withPeerNameVerification();
        $client = $this->buildClient($tlsContext);

        $this->expectException(TlsException::class);
        $this->expectExceptionMessageMatches("/^Connection to '127.0.0.1:\d+' @ '.+' closed during TLS handshake: Peer certificate .+ did not match expected .*`127.0.0.1'$/");

        try {
            $client->request(new Request($httpBin->getServerUri()));
        } finally {
            $httpBin->stopServer();
        }
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testDhKeyTooSmall(): void
    {
        $server = $this->listenWithDhCipher();
        $tlsContext = (new ClientTlsContext(''))
            ->withoutPeerVerification()
            ->withoutPeerNameVerification()
            ->withSecurityLevel(3);
        $client = $this->buildClient($tlsContext);

        $this->expectException(TlsException::class);
        $this->expectExceptionMessageMatches("/^Connection to '127.0.0.1:\d+' @ '.+' closed during TLS handshake: dh key too small$/");

        try {
            $client->request(new Request('https://' . $server->getAddress() . '/'));
        } finally {
            $server->close();
        }
    }

    private function buildClient(ClientTlsContext $tlsContext): HttpClient
    {
        $connectContext = (new ConnectContext)->withTlsContext($tlsContext);
        $connectionFactory = new DefaultConnectionFactory(connectContext: $connectContext);

        return (new HttpClientBuilder)->retry(0)->usingPool(new UnlimitedConnectionPool($connectionFactory))->build();
    }

    private function listenWithDhCipher(): ServerSocket
    {
        $server = listen('127.0.0.1:0');

        $resource = $server->getResource();
        \stream_context_set_option($resource, 'ssl', 'local_cert', __DIR__ . '/tls/amphp.org.pem');
        \stream_context_set_option($resource, 'ssl', 'dh_param', __DIR__ . '/tls/dh2048.pem');
        \stream_context_set_option($resource, 'ssl', 'ciphers', 'DHE-RSA-AES128-GCM-SHA256');

        EventLoop::queue(function () use ($server): void {
            while ($socket = $server->accept()) {
                $this->handshakeOverTls12($socket);
            }
        });

        return $server;
    }

    private function handshakeOverTls12(ResourceSocket $socket): void
    {
        $resource = $socket->getResource();

        do {
            $result = @\stream_socket_enable_crypto($resource, true, \STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
            if ($result === 0) {
                delay(0.01);
            }
        } while ($result === 0);
    }
}
