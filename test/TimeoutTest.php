<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Test;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Failure;
use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use function Amp\asyncCall;

class TimeoutTest extends AsyncTestCase
{
    /** @var Client */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = new Client;
    }

    public function testTimeoutDuringBody(): \Generator
    {
        $server = Socket\Server::listen("tcp://127.0.0.1:0");

        asyncCall(static function () use ($server) {
            /** @var Socket\EncryptableSocket $client */
            while ($client = yield $server->accept()) {
                yield $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                Loop::delay(3000, static function () use ($client) {
                    $client->close();
                });
            }
        });

        $this->setTimeout(2000);

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $request = new Request($uri);
            $request->setTransferTimeout(1000);

            /** @var Response $response */
            $response = yield $this->client->request($request);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1000 ms");

            yield $response->getBody()->buffer();
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringConnect(): \Generator
    {
        $this->setTimeout(600);

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?CancellationToken $token = null
            ): Promise {
                $this->assertSame(1, $connectContext->getConnectTimeout());
                return new Failure(new TimeoutException);
            });

        $this->client = new Client(new DefaultConnectionPool($connector));

        $this->expectException(TimeoutException::class);

        $request = new Request('http://localhost:1337/');
        $request->setTcpConnectTimeout(1);

        yield $this->client->request($request);
    }

    public function testTimeoutDuringTlsEnable(): \Generator
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        asyncCall(static function () use ($server) {
            /** @var Socket\ResourceSocket $client */
            while ($client = yield $server->accept()) {
                Loop::delay(3000, static function () use ($client) {
                    $client->close();
                });
            }
        });

        $this->setTimeout(600);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageRegExp("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 100 ms)");

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(100);

            yield $this->client->request($request);
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringBodyInterceptor(): \Generator
    {
        $server = Socket\Server::listen("tcp://127.0.0.1:0");

        asyncCall(static function () use ($server) {
            /** @var Socket\EncryptableSocket $client */
            while ($client = yield $server->accept()) {
                yield $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                Loop::delay(3000, static function () use ($client) {
                    $client->close();
                });
            }
        });

        $this->setTimeout(2000);

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $request = new Request($uri);

            /** @var Response $response */
            $client = new Client;
            $client->addApplicationInterceptor(new SetRequestTimeout(10000, 10000, 1000));
            $response = yield $client->request($request);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1000 ms");

            yield $response->getBody()->buffer();
        } finally {
            $server->close();
        }

    }

    public function testTimeoutDuringConnectInterceptor(): \Generator
    {
        $this->setTimeout(600);

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?CancellationToken $token = null
            ): Promise {
                $this->assertSame(1, $connectContext->getConnectTimeout());
                return new Failure(new TimeoutException);
            });

        $client = new Client(new DefaultConnectionPool($connector));
        $client->addApplicationInterceptor(new SetRequestTimeout(1));

        $this->expectException(TimeoutException::class);

        $request = new Request('http://localhost:1337/');

        yield $client->request($request);
    }

    public function testTimeoutDuringTlsEnableInterceptor(): \Generator
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        asyncCall(static function () use ($server) {
            /** @var Socket\ResourceSocket $client */
            while ($client = yield $server->accept()) {
                Loop::delay(3000, static function () use ($client) {
                    $client->close();
                });
            }
        });

        $this->setTimeout(600);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageRegExp("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 100 ms)");

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(100);


            $client = new Client();
            $client->addApplicationInterceptor(new SetRequestTimeout(10000, 100));

            yield $client->request($request);
        } finally {
            $server->close();
        }
    }
}
