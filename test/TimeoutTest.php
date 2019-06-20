<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Test\Artax;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\ClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketClient;
use Amp\Http\Client\TimeoutException;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use function Amp\asyncCall;

class TimeoutTest extends AsyncTestCase
{
    /** @var SocketClient */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = (new ClientBuilder)->build();
    }

    public function testTimeoutDuringBody(): \Generator
    {
        $server = Socket\listen("tcp://127.0.0.1:0");

        asyncCall(static function () use ($server) {
            /** @var Socket\EncryptableSocket $client */
            while ($client = yield $server->accept()) {
                yield $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                Loop::delay(3000, static function () use ($client) {
                    $client->close();
                });
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $start = \microtime(true);

            /** @var Response $response */
            $response = yield $this->client->request((new Request($uri))->withTransferTimeout(1000));

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1000 ms");

            yield $response->getBody()->buffer();
        } finally {
            $this->assertLessThan(2, \microtime(true) - $start);
            $server->close();
        }
    }

    public function testTimeoutDuringConnect(): \Generator
    {
        $start = \microtime(true);

        Loop::repeat(1000, function () {
            // dummy watcher, because socket pool doesn't do anything
        });

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?CancellationToken $token = null
            ): Promise {
                $deferred = new Deferred;

                if ($token) {
                    $token->subscribe(static function ($error) use ($deferred) {
                        $deferred->fail($error);
                    });
                }

                return $deferred->promise(); // never resolve
            });

        $this->client = (new ClientBuilder($connector))->build();

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage("Connection to 'localhost:1337' timed out, took longer than 1 ms");

        yield $this->client->request((new Request('http://localhost:1337/'))->withTcpConnectTimeout(1));

        $this->assertLessThan(\microtime(true) - $start, 0.6);
    }

    public function testTimeoutDuringTlsEnable(): \Generator
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        asyncCall(static function () use ($server) {
            /** @var Socket\ResourceSocket $client */
            while ($client = yield $server->accept()) {
                Loop::delay(3000, static function () use ($client) {
                    $client->close();
                });
            }
        });

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $start = \microtime(true);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageRegExp("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 100 ms)");

            yield $this->client->request((new Request($uri))->withTlsHandshakeTimeout(100));
        } finally {
            $this->assertLessThan(0.6, \microtime(true) - $start);
            $server->close();
        }
    }
}
