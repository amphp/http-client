<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Iterator;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\Success;
use function Amp\delay;

class Http1ConnectionTest extends AsyncTestCase
{
    public function testConnectionBusyAfterRequestIsIssued(): \Generator
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);
        $stream->request($request, new NullCancellationToken);
        $stream = null; // gc instance

        $this->assertNull(yield $connection->getStream($request));
    }

    public function testConnectionBusyWithoutRequestButNotGarbageCollected(): \Generator
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @var Stream $stream */
        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = yield $connection->getStream($request);

        $this->assertNull(yield $connection->getStream($request));
    }

    public function testConnectionNotBusyWithoutRequestGarbageCollected(): \Generator
    {
        [$client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://localhost');
        $request->setBody($this->createSlowBody());

        /** @var Stream $stream */
        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = yield $connection->getStream($request);
        /** @noinspection SuspiciousAssignmentsInspection */
        $stream = null; // gc instance

        yield delay(0); // required to clear instance in coroutine :-(

        $this->assertNotNull(yield $connection->getStream($request));
    }

    public function test100Continue(): \Generator
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $request = new Request('http://httpbin.org/post', 'POST');
        $request->setHeader('expect', '100-continue');

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write("HTTP/1.1 100 Continue\r\nFoo: Bar\r\n\r\nHTTP/1.1 204 Nothing to send\r\n\r\n");

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(204, $response->getStatus());
        $this->assertSame('Nothing to send', $response->getReason());
    }

    public function testUpgrade(): \Generator
    {
        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client, 5000);

        $socketData = "Data that should be sent after the upgrade response";

        $invoked = false;
        $callback = function (Socket\EncryptableSocket $socket, Request $request, Response $response) use (&$invoked, $socketData) {
            $invoked = true;
            $this->assertSame(101, $response->getStatus());
            $this->assertSame($socketData, yield $socket->read());
        };

        $request = new Request('http://httpbin.org/upgrade', 'GET');
        $request->setHeader('connection', 'upgrade');
        $request->setUpgradeHandler($callback);

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write("HTTP/1.1 101 Switching Protocols\r\nConnection: Upgrade\r\nUpgrade: test\r\n\r\n" . $socketData);

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertTrue($invoked);
        $this->assertSame(101, $response->getStatus());
        $this->assertSame('Switching Protocols', $response->getReason());
        $this->assertSame([], (yield $response->getTrailers())->getHeaders());
    }

    public function testTransferTimeout(): \Generator
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(600);

        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client);

        $request = new Request('http://localhost');
        $request->setTransferTimeout(500);

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\ntest");

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        try {
            yield $response->getBody()->buffer();
            $this->fail("The request should have timed out");
        } catch (TimeoutException $exception) {
            $this->assertStringContainsString('transfer timeout', $exception->getMessage());
        }
    }

    public function testInactivityTimeout(): \Generator
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(600);

        [$server, $client] = Socket\createPair();

        $connection = new Http1Connection($client);

        $request = new Request('http://localhost');
        $request->setInactivityTimeout(500);

        /** @var Stream $stream */
        $stream = yield $connection->getStream($request);

        $server->write("HTTP/1.1 200 Continue\r\nConnection: keep-alive\r\nContent-Length: 8\r\n\r\ntest");

        /** @var Response $response */
        $response = yield $stream->request($request, new NullCancellationToken);

        $this->assertSame(200, $response->getStatus());

        try {
            yield $response->getBody()->buffer();
            $this->fail("The request should have timed out");
        } catch (TimeoutException $exception) {
            $this->assertStringContainsString('Inactivity timeout', $exception->getMessage());
        }
    }

    private function createSlowBody()
    {
        return new class implements RequestBody {
            public function getHeaders(): Promise
            {
                return new Success([]);
            }

            public function createBodyStream(): InputStream
            {
                return new IteratorStream(Iterator\fromIterable(\array_fill(0, 100, '.'), 1000));
            }

            public function getBodyLength(): Promise
            {
                return new Success(-1);
            }
        };
    }
}
