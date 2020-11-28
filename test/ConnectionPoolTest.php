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
        $response = yield $this->executeRequest($this->createRequest());
        self::assertSame("hello", yield $response->getBody()->buffer());

        yield delay(2000);

        /** @var Response $response */
        $response = yield $this->executeRequest($this->createRequest());
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
        $this->socket->unreference();

        asyncCall(function () {
            yield delay(10);

            /** @var Socket\EncryptableSocket $client */
            $client = yield $this->socket->accept();
            $client->unreference();

            yield $client->write("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            yield delay(1000);

            $client->close();

            yield delay(2000);

            /** @var Socket\EncryptableSocket $client */
            $client = yield $this->socket->accept();
            $client->unreference();

            yield $client->write("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            $client->close();
        });
    }

    private function executeRequest(Request $request, ?CancellationToken $cancellationToken = null): Promise
    {
        return $this->client->request($request, $cancellationToken);
    }

    private function createRequest(): Request
    {
        return new Request('http://' . $this->socket->getAddress());
    }
}
