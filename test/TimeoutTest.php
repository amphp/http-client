<?php

namespace Amp\Test\Artax;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\Client;
use Amp\Http\Client\DefaultClient;
use Amp\Http\Client\HttpSocketPool;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Amp\Socket;
use function Amp\asyncCall;
use function Amp\Promise\wait;

class TimeoutTest extends TestCase
{
    /** @var DefaultClient */
    private $client;

    public function setUp(): void
    {
        $this->client = new DefaultClient;
    }

    public function testTimeoutDuringBody()
    {
        $server = Socket\listen("tcp://127.0.0.1:0");

        asyncCall(function () use ($server) {
            /** @var Socket\EncryptableSocket $client */
            while ($client = yield $server->accept()) {
                yield $client->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n.");

                Loop::delay(3000, function () use ($client) {
                    $client->close();
                });
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $start = \microtime(true);
            $promise = $this->client->request($uri, [Client::OP_TRANSFER_TIMEOUT => 1000]);

            /** @var Response $response */
            $response = wait($promise);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded: 1000 ms");

            wait($response->getBody()->buffer());
        } finally {
            $this->assertLessThan(2, \microtime(true) - $start);
            $server->close();
        }
    }

    public function testTimeoutDuringConnect()
    {
        Loop::repeat(1000, function () { /* dummy watcher, because socket pool doesn't do anything */ });

        $this->client = new DefaultClient(null, new HttpSocketPool(new class implements Socket\SocketPool {
            public function checkout(string $uri, ?Socket\ConnectContext $connectContext = null, ?CancellationToken $token = null): Promise
            {
                $deferred = new Deferred;

                if ($token) {
                    $token->subscribe(function ($error) use ($deferred) {
                        $deferred->fail($error);
                    });
                }

                return $deferred->promise(); // never resolve
            }

            public function checkin(Socket\EncryptableSocket $socket): void
            {
                // ignore
            }

            public function clear(Socket\EncryptableSocket $socket): void
            {
                // ignore
            }
        }));

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage("Allowed transfer timeout exceeded: 100 ms");

        $this->assertRunTimeLessThan(function () {
            wait($this->client->request("http://localhost:1337/", [Client::OP_TRANSFER_TIMEOUT => 100]));
        }, 600);
    }

    public function testTimeoutDuringTlsEnable()
    {
        $tlsContext = (new Socket\ServerTlsContext)
            ->withDefaultCertificate(new Socket\Certificate(__DIR__ . "/tls/amphp.org.pem"));

        $server = Socket\listen("tcp://127.0.0.1:0", null, $tlsContext);

        asyncCall(function () use ($server) {
            /** @var Socket\ResourceSocket $client */
            while ($client = yield $server->accept()) {
                Loop::delay(3000, function () use ($client) {
                    $client->close();
                });
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $start = \microtime(true);
            $promise = $this->client->request($uri, [Client::OP_TRANSFER_TIMEOUT => 100]);

            $this->expectException(TimeoutException::class);
            $this->expectExceptionMessage("Allowed transfer timeout exceeded: 100 ms");
            wait($promise);
        } finally {
            $this->assertLessThan(0.6, \microtime(true) - $start);
            $server->close();
        }
    }
}
