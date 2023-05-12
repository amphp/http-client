<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\ReadableBuffer;
use Amp\Future;
use Amp\Http\Client\Connection\Internal\Http2Stream;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\Trailers;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\InternetAddress;

class HttpStreamTest extends AsyncTestCase
{
    public function testCallsReleaseCallbackOnDestruct(): void
    {
        $connection = $this->createConfiguredMock(Connection::class, [
            'getLocalAddress' => new InternetAddress('127.0.0.1', 80),
            'getRemoteAddress' => new InternetAddress('127.0.0.1', 80),
        ]);

        $request = new Request('http://httpbin.org');
        $response = new Response('1.1', 200, null, [], new ReadableBuffer, $request, Future::complete(new Trailers([])));
        $requestCallback = $this->createCallback(1, fn () => $response);
        $releaseCallback = $this->createCallback(1);

        $httpStream = HttpStream::fromConnection($connection, $requestCallback, $releaseCallback);

        $httpStream->request($request, new NullCancellation);
    }
}
