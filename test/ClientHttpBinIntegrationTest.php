<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Delayed;
use Amp\Http\Client\Body\FileBody;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\Interceptor\SetDefaultHeader;
use Amp\Http\Client\Interceptor\FollowRedirects;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\TooManyRedirectsException;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Producer;
use Amp\Promise;
use Amp\Socket;
use Amp\Success;
use Psr\Log\NullLogger;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\Iterator\fromIterable;
use function Amp\Promise\wait;

class ClientHttpBinIntegrationTest extends AsyncTestCase
{
    /** @var Socket\Server */
    private $socket;
    /** @var Socket\Server */
    private $server;
    /** @var Client */
    private $client;
    /** @var callable */
    private $responseCallback;

    public function testHttp10Response(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.0 200 OK\r\n\r\n");

        /** @var Response $response */
        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame("", yield $response->getBody()->buffer());
    }

    public function testCloseAfterConnect(): \Generator
    {
        $this->givenRawServerResponse("");

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage("Receiving the response headers failed, because the socket closed early");

        /** @var Response $response */
        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);
        yield $this->executeRequest($request);
    }

    public function testIncompleteHttpResponseWithContentLength(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\n.");

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage("Socket disconnected prior to response completion");

        /** @var Response $response */
        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);

        $response = yield $this->executeRequest($request);
        yield $response->getBody()->buffer();
    }

    public function testIncompleteHttpResponseWithChunkedEncoding(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.0 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n0\r"); // missing \n

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage("Socket disconnected prior to response completion");

        /** @var Response $response */
        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);

        $response = yield $this->executeRequest($request);
        yield $response->getBody()->buffer();
    }

    public function testIncompleteHttpResponseWithoutChunkedEncodingAndWithoutContentLength(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\n\r\n00000000000");

        /** @var Response $response */
        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);

        $response = yield $this->executeRequest($request);
        $body = yield $response->getBody()->buffer();

        self::assertSame('00000000000', $body);
    }

    public function testDefaultUserAgentSent(): \Generator
    {
        $uri = 'http://httpbin.org/user-agent';

        $this->givenNetworkInterceptor(new SetDefaultHeader('user-agent', 'amphp/http-client'));

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);

        $body = yield $response->getBody()->buffer();
        $result = \json_decode($body, true);

        $this->assertSame('amphp/http-client', $result['user-agent']);
    }

    public function testCustomUserAgentSentIfAssigned(): \Generator
    {
        $uri = 'http://httpbin.org/user-agent';
        $customUserAgent = 'test-user-agent';

        $request = new Request($uri);
        $request->setHeader('User-Agent', $customUserAgent);
        $request->setHeader('Connection', 'keep-alive');

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $body = yield $response->getBody()->buffer();
        $result = \json_decode($body, true);

        $this->assertSame($customUserAgent, $result['user-agent']);
    }

    public function testPostStringBody(): \Generator
    {
        $body = 'zanzibar';
        $request = new Request('http://httpbin.org/post');
        $request->setMethod('POST');
        $request->setBody($body);

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals($body, $result['data']);
    }

    public function testPutStringBody(): \Generator
    {
        $uri = 'http://httpbin.org/put';

        $body = 'zanzibar';
        $request = new Request($uri, "PUT");
        $request->setBody($body);

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals($body, $result['data']);
    }

    /**
     * @dataProvider provideStatusCodes
     *
     * @param $statusCode
     *
     * @return \Generator
     * @throws \Throwable
     */
    public function testStatusCodeResponses($statusCode): \Generator
    {
        /** @var Response $response */
        $response = yield $this->executeRequest(new Request("http://httpbin.org/status/{$statusCode}"));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($statusCode, $response->getStatus());
    }

    public function provideStatusCodes(): array
    {
        return [
            [200],
            [400],
            [404],
            [500],
        ];
    }

    public function testReason(): \Generator
    {
        /** @var Response $response */
        $response = yield $this->executeRequest(new Request("http://httpbin.org/status/418"));

        $this->assertInstanceOf(Response::class, $response);

        $expectedReason = "I'M A TEAPOT";
        $actualReason = $response->getReason();

        $this->assertSame($expectedReason, $actualReason);
    }

    public function testRedirect(): \Generator
    {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url=" . \rawurlencode($redirectTo);

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatus());

        $originalUri = $response->getOriginalRequest()->getUri();

        $this->assertSame($uri, (string) $originalUri);
    }

    public function testRedirectWithFollow(): \Generator
    {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url=" . \rawurlencode($redirectTo);

        $this->givenApplicationInterceptor(new FollowRedirects);

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($statusCode, $response->getStatus());

        $originalUri = $response->getOriginalRequest()->getUri();

        $this->assertSame($uri, (string) $originalUri);
    }

    public function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost(): \Generator
    {
        $uri = 'http://httpbin.org/post';

        $request = new Request($uri);
        $request->setMethod('POST');

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody()->buffer());
        $result = \json_decode($body, true);

        $this->assertEquals('0', $result['headers']['Content-Length']);
    }

    public function testFormEncodedBodyRequest(): \Generator
    {
        $body = new FormBody;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);

        $request = new Request('http://httpbin.org/post', "POST");
        $request->setBody($body);

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals($field2, $result['form']['field2']);
        $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }

    public function testFileBodyRequest(): \Generator
    {
        $uri = 'http://httpbin.org/post';

        $bodyPath = __DIR__ . '/fixture/answer.txt';
        $body = new FileBody($bodyPath);

        $request = new Request($uri, "POST");
        $request->setBody($body);

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertStringEqualsFile($bodyPath, $result['data']);
    }

    public function testMultipartBodyRequest(): \Generator
    {
        $field1 = 'test val';
        $file1 = __DIR__ . '/fixture/lorem.txt';
        $file2 = __DIR__ . '/fixture/answer.txt';

        $boundary = 'AaB03x';

        $body = new FormBody($boundary);
        $body->addFields(['field1' => $field1]);
        $body->addFiles(['file1' => $file1, 'file2' => $file2]);

        $request = new Request('http://httpbin.org/post', "POST");
        $request->setBody($body);

        /** @var Response $response */
        $response = yield $this->executeRequest($request);

        $this->assertEquals(200, $response->getStatus());

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertStringEqualsFile($file1, $result['files']['file1']);
        $this->assertStringEqualsFile($file2, $result['files']['file2']);
        $this->assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }

    /**
     * @requires extension zlib
     */
    public function testGzipResponse(): \Generator
    {
        $this->givenNetworkInterceptor(new DecompressResponse);

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request('http://httpbin.org/gzip'));

        $this->assertEquals(200, $response->getStatus());

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertTrue($result['gzipped']);
    }

    /**
     * @requires extension zlib
     */
    public function testDeflateResponse(): \Generator
    {
        $this->givenNetworkInterceptor(new DecompressResponse);

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request('http://httpbin.org/deflate'));

        $this->assertEquals(200, $response->getStatus());

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertTrue($result['deflated']);
    }

    public function testInfiniteRedirect(): \Generator
    {
        $this->givenApplicationInterceptor(new FollowRedirects);

        $this->expectException(TooManyRedirectsException::class);

        yield $this->executeRequest(new Request("http://httpbin.org/redirect/11"));
    }

    public function testRequestCancellation(): \Generator
    {
        $this->givenSlowRawServerResponse(
            100,
            "HTTP/1.1 200 OK\r\nConnection: close\r\nContent-Length: 1000\r\n\r\n",
            ...\array_fill(0, 1000, '.')
        );

        $cancellationTokenSource = new CancellationTokenSource;
        /** @var Response $response */
        $response = yield $this->executeRequest($this->createRequest(), $cancellationTokenSource->getToken());
        $cancellationTokenSource->cancel();
        $this->expectException(CancelledException::class);
        yield $response->getBody()->buffer();
    }

    public function testContentLengthBodyMismatchWithTooManyBytesSimple(): \Generator
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Body contained more bytes than specified in Content-Length, aborting request");

        $request = new Request("http://httpbin.org/post", "POST");
        $request->setBody(new class implements RequestBody {
            public function getHeaders(): Promise
            {
                return new Success([]);
            }

            public function createBodyStream(): InputStream
            {
                return new InMemoryStream("foo");
            }

            public function getBodyLength(): Promise
            {
                return new Success(1);
            }
        });

        yield $this->executeRequest($request);
    }

    public function testContentLengthBodyMismatchWithTooManyBytesWith3ByteChunksAndLength2(): \Generator
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Body contained more bytes than specified in Content-Length, aborting request");

        $request = new Request("http://httpbin.org/post", "POST");
        $request->setBody(new class implements RequestBody {
            public function getHeaders(): Promise
            {
                return new Success([]);
            }

            public function createBodyStream(): InputStream
            {
                return new IteratorStream(fromIterable(["a", "b", "c"], 500));
            }

            public function getBodyLength(): Promise
            {
                return new Success(2);
            }
        });

        yield $this->executeRequest($request);
    }

    public function testContentLengthBodyMismatchWithTooFewBytes(): \Generator
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Body contained fewer bytes than specified in Content-Length, aborting request");

        $request = new Request("http://httpbin.org/post", "POST");
        $request->setBody(new class implements RequestBody {
            public function getHeaders(): Promise
            {
                return new Success([]);
            }

            public function createBodyStream(): InputStream
            {
                return new InMemoryStream("foo");
            }

            public function getBodyLength(): Promise
            {
                return new Success(42);
            }
        });

        yield $this->executeRequest($request);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client;

        if ($this->socket) {
            $this->socket->close();
        }

        $this->socket = Socket\Server::listen('127.0.0.1:0');
        $this->socket->unreference();
        $this->server = new Server([$this->socket], new CallableRequestHandler(static function () {
            return new \Amp\Http\Server\Response(Status::OK, [], new IteratorStream(new Producer(static function (
                $emit
            ) {
                yield $emit(".");
                yield new Delayed(5000);
                yield $emit(".");
            })));
        }), new NullLogger);

//         wait($this->server->start());

        asyncCall(function () {
            /** @var Socket\EncryptableSocket $client */
            $client = yield $this->socket->accept();
            yield ($this->responseCallback)($client);
        });
    }

    private function givenRawServerResponse(string $response): void
    {
        $this->responseCallback = static function (Socket\Socket $socket) use ($response) {
            return $socket->write($response);
        };
    }

    private function givenSlowRawServerResponse(int $delay, string ...$chunks): void
    {
        $this->responseCallback = static function (Socket\Socket $socket) use ($delay, $chunks) {
            return call(static function () use ($socket, $delay, $chunks) {
                foreach ($chunks as $chunk) {
                    $socket->write($chunk);
                    yield new Delayed($delay);
                }
            });
        };
    }

    private function executeRequest(Request $request, ?CancellationToken $cancellationToken = null): Promise
    {
        return $this->client->request($request, $cancellationToken);
    }

    private function createRequest(): Request
    {
        return new Request('http://' . $this->socket->getAddress());
    }

    private function givenApplicationInterceptor(ApplicationInterceptor $interceptor): void
    {
        $this->client->addApplicationInterceptor($interceptor);
    }

    private function givenNetworkInterceptor(NetworkInterceptor $interceptor): void
    {
        $this->client->addNetworkInterceptor($interceptor);
    }
}
