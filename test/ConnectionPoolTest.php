<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use function Amp\asyncCall;
use function Amp\delay;

class ConnectionPoolTest extends AsyncTestCase
{
    /** @var Socket\Server */
    private $socket;
    /** @var HttpClient */
    private $client;

    public function testConnectionCloseWhileIdle(): \Generator
    {
        /** @var Response $response */
        $response = yield $this->executeRequest($this->createRequest(1));
        self::assertSame("hello", yield $response->getBody()->buffer());

        yield delay(1000);

        /** @var Response $response */
        $response = yield $this->executeRequest($this->createRequest(2));
        self::assertSame("hello", yield $response->getBody()->buffer());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = (new HttpClientBuilder)->retry(0)->build();

        if ($this->socket) {
            $this->socket->close();
        }

        $this->socket = Socket\Server::listen('127.0.0.1:0');

        asyncCall(function () {
            /** @var Socket\EncryptableSocket $client */
            $client = yield $this->socket->accept();

            yield $client->read();
            yield $client->write("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            yield delay(500);

            $client->close();

            yield delay(1000);

            /** @var Socket\EncryptableSocket $client */
            $client = yield $this->socket->accept();

            yield $client->read();
            yield $client->end("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            $this->socket->close();
        });
    }

    private function executeRequest(Request $request, ?CancellationToken $cancellationToken = null): Promise
    {
        return $this->client->request($request, $cancellationToken);
    }

    private function createRequest(int $num): Request
    {
        return new Request('http://' . $this->socket->getAddress() . '/' . $num);
    }
}
