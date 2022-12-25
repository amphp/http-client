<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use function Amp\async;
use function Amp\delay;
use function Amp\Socket\listen;

class ConnectionPoolTest extends AsyncTestCase
{
    private Socket\SocketServer $socket;

    private HttpClient $client;

    public function testConnectionCloseWhileIdle(): void
    {
        $response = $this->executeRequest($this->createRequest(1));
        self::assertSame("hello", $response->getBody()->buffer());

        delay(0.1);

        $response = $this->executeRequest($this->createRequest(2));
        self::assertSame("hello", $response->getBody()->buffer());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = (new HttpClientBuilder)->retry(0)->build();

        $this->socket = listen('127.0.0.1:0');

        async(function () {
            $client = $this->socket->accept();

            self::assertNotNull($client);

            $client->read();
            $client->write("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");

            delay(0.05);

            $client->close();

            delay(0.1);

            $client = $this->socket->accept();

            $client->read();
            $client->write("HTTP/1.1 200 OK\r\nconnection: keep-alive\r\ncontent-length: 5\r\n\r\nhello");
            $client->end();

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
