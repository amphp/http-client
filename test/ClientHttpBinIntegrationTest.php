<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Http\Client\Body\FileBody;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\ModifyRequest;
use Amp\Http\Client\Interceptor\SetRequestHeaderIfUnset;
use Amp\Http\Client\Interceptor\TooManyRedirectsException;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Cookie\ResponseCookie;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\Success;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\delay;
use function Amp\Iterator\fromIterable;

class ClientHttpBinIntegrationTest extends AsyncTestCase
{
    /** @var Socket\Server */
    private $socket;
    /** @var HttpClient */
    private $client;
    /** @var HttpClientBuilder */
    private $builder;
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

        $this->client = $this->builder->retry(0)->build();

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

    public function testDuplicateContentLengthHeader(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: 1\r\ncontent-length: 2\r\n\r\n\r\n\r\n\r\n");

        $this->expectException(ParseException::class);

        yield $this->executeRequest($this->createRequest());
    }

    public function testInvalidContentLengthHeader(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: foobar\r\n\r\n\r\n\r\n\r\n");

        $this->expectException(ParseException::class);

        yield $this->executeRequest($this->createRequest());
    }

    public function testFoldedHeader(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: 0\r\nfoo: hello\r\n world\r\n\r\n");

        /** @var Response $response */
        $response = yield $this->executeRequest($this->createRequest());
        yield $response->getBody()->buffer();

        $this->assertSame(['hello world'], $response->getHeaderArray('foo'));
    }

    public function testInvalidHeaders(): \Generator
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: 0\r\nfoo\x2f: bar\r\n\r\n");

        $this->expectException(ParseException::class);

        yield $this->executeRequest($this->createRequest());
    }

    public function testDefaultUserAgentSent(): \Generator
    {
        $uri = 'http://httpbin.org/user-agent';

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);

        $body = yield $response->getBody()->buffer();
        $result = \json_decode($body, true);

        $this->assertSame('amphp/http-client @ v4.x', $result['user-agent']);
    }

    public function testDefaultUserAgentCanBeChanged(): \Generator
    {
        $uri = 'http://httpbin.org/user-agent';

        $this->givenNetworkInterceptor(new SetRequestHeaderIfUnset('user-agent', 'amphp/http-client'));

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);

        $body = yield $response->getBody()->buffer();
        $result = \json_decode($body, true);

        $this->assertSame('amphp/http-client', $result['user-agent']);
    }

    public function testHttp2Push(): \Generator
    {
        $request = new Request('https://http2-server-push-demo.keksi.io/');
        $request->setPushHandler(static function (Request $request, Promise $response) {
            self::assertSame('/image.jpg', $request->getUri()->getPath());
            self::assertSame('image/jpeg', (yield $response)->getHeader('content-type'));
        });

        yield $this->executeRequest($request);
    }

    public function testGzipBomb(): \Generator
    {
        $this->markTestSkipped('Run this manually');

        /** @var Response $response */
        $response = yield $this->client->request(new Request('https://blog.haschek.at/tools/bomb.php'));

        $sessionCookie = ResponseCookie::fromHeader($response->getHeader('set-cookie'));
        $body = yield $response->getBody()->buffer();
        \preg_match('(bombme=[a-f0-9]+)', $body, $match);

        $request = new Request('https://blog.haschek.at/tools/bomb.php?' . $match[0]);
        $request->setHeader('cookie', new RequestCookie($sessionCookie->getName(), $sessionCookie->getValue()));
        $response = yield $this->client->request($request);

        $this->expectException(ParseException::class);

        if (\method_exists($this, 'expectExceptionMessageMatches')) {
            $this->expectExceptionMessageMatches('(Configured body size exceeded: \d+ bytes received, while the configured limit is 10485760 bytes)');
        } else {
            /** @noinspection PhpDeprecationInspection */
            $this->expectExceptionMessageRegExp('(Configured body size exceeded: \d+ bytes received, while the configured limit is 10485760 bytes)');
        }

        yield $response->getBody()->buffer();
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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

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

        $this->client = $this->builder->followRedirects(0)->build();

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatus());

        $originalUri = $response->getOriginalRequest()->getUri();
        $this->assertSame($response, $response->getOriginalResponse());

        $this->assertSame($uri, (string) $originalUri);
    }

    public function testRedirectWithFollow(): \Generator
    {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url=" . \rawurlencode($redirectTo);

        $this->client = $this->builder->followRedirects()->build();

        /** @var Response $response */
        $response = yield $this->executeRequest(new Request($uri));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($statusCode, $response->getStatus());

        $originalUri = $response->getOriginalRequest()->getUri();
        $this->assertSame(302, $response->getOriginalResponse()->getStatus());

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

        $body = yield $response->getBody()->buffer();
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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

        $this->assertTrue($result['gzipped']);
        $this->assertFalse($response->hasHeader('content-encoding'));
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

        $result = \json_decode(yield $response->getBody()->buffer(), true);

        $this->assertTrue($result['deflated']);
    }

    public function testInfiniteRedirect(): \Generator
    {
        $this->builder->followRedirects(10);

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
        $this->expectException(InvalidRequestException::class);
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
        $this->expectException(InvalidRequestException::class);
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
        $this->expectException(InvalidRequestException::class);
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

    public function testHttp2Support(): \Generator
    {
        /** @var Response $response */
        $response = yield $this->client->request(new Request('https://http2.pro/api/v1'));
        $body = yield $response->getBody()->buffer();
        $json = \json_decode($body, true);

        $this->assertSame(1, $json['http2']);
        $this->assertSame('HTTP/2.0', $json['protocol']);
        $this->assertSame(1, $json['push']);
        $this->assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2SupportBody(): \Generator
    {
        $request = new Request('https://http2.pro/api/v1', 'POST');
        $request->setBody('foobar');

        /** @var Response $response */
        $response = yield $this->client->request($request);
        $body = yield $response->getBody()->buffer();
        $json = \json_decode($body, true);

        $this->assertSame(1, $json['http2']);
        $this->assertSame('HTTP/2.0', $json['protocol']);
        $this->assertSame(1, $json['push']);
        $this->assertSame('2', $response->getProtocolVersion());
    }

    public function testConcurrentSlowNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new ModifyRequest(static function (Request $request) {
            yield delay(5000);

            return $request;
        }));

        /** @var Response $response1 */
        $response1 = yield $this->client->request(new Request('https://http2.pro/api/v1'));

        /** @var Response $response2 */
        $response2 = yield $this->client->request(new Request('https://http2.pro/api/v1'));

        $body1 = yield $response1->getBody()->buffer();
        $body2 = yield $response2->getBody()->buffer();

        $json1 = \json_decode($body1, true);
        $json2 = \json_decode($body2, true);

        $this->assertSame(1, $json1['http2']);
        $this->assertSame(1, $json2['http2']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new HttpClientBuilder;
        $this->client = $this->builder->build();

        if ($this->socket) {
            $this->socket->close();
        }

        $this->socket = Socket\Server::listen('127.0.0.1:0');
        $this->socket->unreference();

        asyncCall(function () {
            /** @var Socket\EncryptableSocket $client */
            $client = yield $this->socket->accept();
            $client->unreference();
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
                    $delay = delay($delay);
                    $delay->unreference();
                    yield $delay;
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
        $this->builder = $this->builder->intercept($interceptor);
        $this->client = $this->builder->build();
    }

    private function givenNetworkInterceptor(NetworkInterceptor $interceptor): void
    {
        $this->builder = $this->builder->interceptNetwork($interceptor);
        $this->client = $this->builder->build();
    }
}
