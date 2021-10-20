<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Revolt\EventLoop;
use function Amp\delay;

class ConnectionPoolTest extends AsyncTestCase
{
    private Socket\Server $socket;

    private HttpClient $client;

    public function testConnectionCloseWhileIdle(): void
    {
        $response = $this->executeRequest($this->createRequest(1));
        self::assertSame("hello", $response->getBody()->buffer());

        delay(1);

        $response = $this->executeRequest($this->createRequest(2));
        self::assertSame("hello", $response->getBody()->buffer());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = (new HttpClientBuilder)->retry(0)->build();

        $this->socket = Socket\Server::listen('127.0.0.1:0');

        EventLoop::queue(function () {
            $client = $this->socket->accept();

            $client->read();
            $client->write("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            $client->close();

            $client = $this->socket->accept();

            $client->read();
            $client->end("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            $this->socket->close();
        });
    }

    private function executeRequest(Request $request): Response
    {
        return $this->client->request($request);
    }

    private function createRequest(int $num): Request
    {
        return new Request('http://' . $this->socket->getAddress() . '/' . $num);
    }
}
