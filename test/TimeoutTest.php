<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Revolt\EventLoop;

class TimeoutTest extends AsyncTestCase
{
    private DelegateHttpClient $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = HttpClientBuilder::buildDefault();
    }

    public function testTimeoutDuringBody(): void
    {
        $server = Socket\Server::listen("tcp://127.0.0.1:0");

        EventLoop::queue(static function () use ($server): void {
            while ($client = $server->accept()) {
                $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                EventLoop::unreference(EventLoop::delay(3, static function () use ($client) {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(2);

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $request = new Request($uri);
            $request->setTransferTimeout(1);

            $response = $this->client->request($request);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1 s");

            $response->getBody()->buffer();
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringConnect(): void
    {
        $this->setTimeout(0.6);

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?Cancellation $token = null
            ): Socket\EncryptableSocket {
                $this->assertSame(0.001, $connectContext->getConnectTimeout());
                throw new TimeoutException;
            });

        $this->client = new PooledHttpClient(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)));

        $this->expectException(TimeoutException::class);

        $request = new Request('http://localhost:1337/');
        $request->setTcpConnectTimeout(0.001);

        $this->client->request($request, new NullCancellation);
    }

    public function testTimeoutDuringTlsEnable(): void
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        EventLoop::queue(static function () use ($server): void {
            while ($client = $server->accept()) {
                EventLoop::unreference(EventLoop::delay(3, static function () use ($client): void {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(0.6);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageMatches("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 0.1 s)");

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(0.1);

            try {
                $this->client->request($request);
            } catch (UnprocessedRequestException $e) {
                throw $e->getPrevious();
            }
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringTlsEnableCatchable(): void
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        EventLoop::queue(static function () use ($server): void {
            while ($client = $server->accept()) {
                EventLoop::unreference(EventLoop::delay(3, static function () use ($client): void {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(0.6);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(0.1);

            $this->client->request($request);

            self::fail('No exception thrown');
        } catch (UnprocessedRequestException $e) {
            self::assertStringStartsWith('TLS handshake with \'127.0.0.1:', $e->getPrevious()->getMessage());
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringBodyInterceptor(): void
    {
        $server = Socket\Server::listen("tcp://127.0.0.1:0");

        EventLoop::queue(static function () use ($server): void {
            while ($client = $server->accept()) {
                $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                EventLoop::unreference(EventLoop::delay(3, static function () use ($client): void {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(2);

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $request = new Request($uri);

            $client = new InterceptedHttpClient(new PooledHttpClient, new SetRequestTimeout(10, 10, 1));
            $response = $client->request($request, new NullCancellation);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded, took longer than 1 s");

            $response->getBody()->buffer();
        } finally {
            $server->close();
        }
    }

    public function testTimeoutDuringConnectInterceptor(): void
    {
        $this->setTimeout(0.6);

        $connector = $this->createMock(Socket\Connector::class);
        $connector->method('connect')
            ->willReturnCallback(function (
                string $uri,
                ?Socket\ConnectContext $connectContext = null,
                ?Cancellation $token = null
            ): Socket\EncryptableSocket {
                $this->assertSame(0.001, $connectContext->getConnectTimeout());
                throw new TimeoutException;
            });

        $client = new PooledHttpClient(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)));
        $client = new InterceptedHttpClient($client, new SetRequestTimeout(0.001));

        $this->expectException(TimeoutException::class);

        $request = new Request('http://localhost:1337/');

        $client->request($request, new NullCancellation);
    }

    public function testTimeoutDuringTlsEnableInterceptor(): void
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\Server::listen("tcp://127.0.0.1:0", (new Socket\BindContext)->withTlsContext($tlsContext));

        EventLoop::queue(static function () use ($server): void {
            while ($client = $server->accept()) {
                EventLoop::unreference(EventLoop::delay(3, static function () use ($client) {
                    $client->close();
                }));
            }
        });

        $this->setTimeout(0.6);

        try {
            $uri = "https://" . $server->getAddress() . "/";

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessageMatches("(TLS handshake with '127.0.0.1:\d+' @ '127.0.0.1:\d+' timed out, took longer than 0.1 s)");

            $request = new Request($uri);
            $request->setTlsHandshakeTimeout(0.1);

            $client = new PooledHttpClient();
            $client = new InterceptedHttpClient($client, new SetRequestTimeout(10, 0.1));

            try {
                $client->request($request, new NullCancellation);
            } catch (UnprocessedRequestException $e) {
                throw $e->getPrevious();
            }
        } finally {
            $server->close();
        }
    }
}
