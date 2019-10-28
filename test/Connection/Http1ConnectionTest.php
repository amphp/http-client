<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
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

        $request = new Request('http://example.com');
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

        $request = new Request('http://example.com');
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

        $request = new Request('http://example.com');
        $request->setBody($this->createSlowBody());

        /** @var Stream $stream */
        /** @noinspection PhpUnusedLocalVariableInspection */
        $stream = yield $connection->getStream($request);
        /** @noinspection SuspiciousAssignmentsInspection */
        $stream = null; // gc instance

        yield delay(0); // required to clear instance in coroutine :-(

        $this->assertNotNull(yield $connection->getStream($request));
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
