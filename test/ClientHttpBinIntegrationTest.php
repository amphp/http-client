<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\Interceptor\DecompressResponse;
use Amp\Http\Client\Interceptor\ModifyRequest;
use Amp\Http\Client\Interceptor\SetRequestHeaderIfUnset;
use Amp\Http\Client\Interceptor\TooManyRedirectsException;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Http\Http1\Rfc7230;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Pipeline;
use Amp\Socket;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

class ClientHttpBinIntegrationTest extends AsyncTestCase
{
    private Socket\ServerSocket $socket;

    private HttpClient $client;

    private HttpClientBuilder $builder;

    private ?\Closure $responseCallback = null;

    private ?string $rawHandler = null;

    public function testHttp10Response(): void
    {
        $this->givenRawServerResponse("HTTP/1.0 200 OK\r\n\r\n");

        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);
        $response = $this->executeRequest($request);

        self::assertSame("", $response->getBody()->buffer());
    }

    public function testCloseAfterConnect(): void
    {
        $this->givenRawServerResponse("");

        $this->expectException(SocketException::class);
        $this->expectExceptionMessageMatches("(Receiving the response headers for '.*' failed, because the socket to '.*' @ '.*' closed early)");

        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);
        $this->executeRequest($request);
    }

    public function testIncompleteHttpResponseWithContentLength(): void
    {
        $this->givenRawServerResponse("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\n.");

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage("Socket disconnected prior to response completion");

        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);

        $response = $this->executeRequest($request);
        $response->getBody()->buffer();
    }

    public function testIncompleteHttpResponseWithChunkedEncoding(): void
    {
        $this->givenRawServerResponse("HTTP/1.0 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n0\r"); // missing \n

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage("Socket disconnected prior to response completion");

        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);

        $response = $this->executeRequest($request);
        $response->getBody()->buffer();
    }

    public function testIncompleteHttpResponseWithoutChunkedEncodingAndWithoutContentLength(): void
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\n\r\n00000000000");

        $request = $this->createRequest();
        $request->setProtocolVersions(["1.0"]);

        $response = $this->executeRequest($request);
        $body = $response->getBody()->buffer();

        self::assertSame('00000000000', $body);
    }

    public function testDuplicateContentLengthHeader(): void
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: 1\r\ncontent-length: 2\r\n\r\n\r\n\r\n\r\n");

        $this->expectException(ParseException::class);

        $this->executeRequest($this->createRequest());
    }

    public function testInvalidContentLengthHeader(): void
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: foobar\r\n\r\n\r\n\r\n\r\n");

        $this->expectException(ParseException::class);

        $this->executeRequest($this->createRequest());
    }

    public function testFoldedHeader(): void
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: 0\r\nfoo: hello\r\n world\r\n\r\n");

        $response = $this->executeRequest($this->createRequest());
        $response->getBody()->buffer();

        self::assertSame(['hello world'], $response->getHeaderArray('foo'));
    }

    public function testInvalidHeaders(): void
    {
        $this->givenRawServerResponse("HTTP/1.1 200 OK\r\ncontent-length: 0\r\nfoo\x2f: bar\r\n\r\n");

        $this->expectException(ParseException::class);

        $this->executeRequest($this->createRequest());
    }

    public function testDefaultUserAgentSent(): void
    {
        $uri = 'http://httpbin.org/user-agent';

        $response = $this->executeRequest(new Request($uri));

        self::assertInstanceOf(Response::class, $response);

        $body = $response->getBody()->buffer();
        $result = \json_decode($body, true);

        self::assertSame('amphp/http-client @ v5.x', $result['user-agent']);
    }

    public function testDefaultUserAgentCanBeChanged(): void
    {
        $uri = 'http://httpbin.org/user-agent';

        $this->givenNetworkInterceptor(new SetRequestHeaderIfUnset('user-agent', 'amphp/http-client'));

        $response = $this->executeRequest(new Request($uri));

        self::assertInstanceOf(Response::class, $response);

        $body = $response->getBody()->buffer();
        $result = \json_decode($body, true);

        self::assertSame('amphp/http-client', $result['user-agent']);
    }

    public function testHeaderCase(): void
    {
        $this->responseCallback = static function (Socket\Socket $socket): void {
            $buffer = '';

            while (null !== $chunk = $socket->read()) {
                $buffer .= $chunk;

                if (\str_contains($buffer, "\r\n\r\n")) {
                    break;
                }
            }

            $headers = \explode("\r\n", \trim($buffer));
            \array_shift($headers);

            $buffer = \json_encode(Rfc7230::parseHeaderPairs(\implode("\r\n", $headers) . "\r\n"));

            $socket->write("HTTP/1.0 200 OK\r\n\r\n$buffer");
        };

        $request = $this->createRequest();
        $request->setHeader('tEst', 'test');

        $response = $this->executeRequest($request);

        $body = $response->getBody()->buffer();
        $result = \json_decode($body, true);

        self::assertSame([
            ['tEst', 'test'],
            ['accept', '*/*'],
            ['user-agent', 'amphp/http-client @ v5.x'],
            ['Accept-Encoding', 'gzip, deflate, identity'],
            ['host', (string) $this->socket->getAddress()],
        ], $result);
    }

    public function testHttp2Push(): void
    {
        $request = new Request('https://http2-server-push-demo.keksi.io/');
        $request->setPushHandler(static function (Request $request, Future $response) use (
            &$path,
            &$contentType,
            &$future,
        ): void {
            $future = $response;

            $path = $request->getUri()->getPath();
            $contentType = $response->await()->getHeader('content-type');
        });

        $this->executeRequest($request);

        $future->await();

        self::assertSame('/image.jpg', $path);
        self::assertSame('image/jpeg', $contentType);
    }

    public function testGzipBomb(): void
    {
        self::markTestSkipped('Run this manually');

        $response = $this->client->request(new Request('https://blog.haschek.at/tools/bomb.php'));

        $sessionCookie = ResponseCookie::fromHeader($response->getHeader('set-cookie'));
        $body = $response->getBody()->buffer();
        \preg_match('(bombme=[a-f0-9]+)', $body, $match);

        $request = new Request('https://blog.haschek.at/tools/bomb.php?' . $match[0]);
        $request->setHeader('cookie', new RequestCookie($sessionCookie->getName(), $sessionCookie->getValue()));
        $response = $this->client->request($request);

        $this->expectException(ParseException::class);

        $this->expectExceptionMessageMatches('(Configured body size exceeded: \d+ bytes received, while the configured limit is 10485760 bytes)');

        $response->getBody()->buffer();
    }

    public function testCustomUserAgentSentIfAssigned(): void
    {
        $uri = 'http://httpbin.org/user-agent';
        $customUserAgent = 'test-user-agent';

        $request = new Request($uri);
        $request->setHeader('User-Agent', $customUserAgent);
        $request->setHeader('Connection', 'keep-alive');

        $response = $this->executeRequest($request);

        self::assertInstanceOf(Response::class, $response);

        $body = $response->getBody()->buffer();
        $result = \json_decode($body, true);

        self::assertSame($customUserAgent, $result['user-agent']);
    }

    public function testPostStringBody(): void
    {
        $body = 'zanzibar';
        $request = new Request('http://httpbin.org/post');
        $request->setMethod('POST');
        $request->setBody(BufferedContent::text($body));

        $response = $this->executeRequest($request);

        self::assertInstanceOf(Response::class, $response);

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertEquals($body, $result['data']);
    }

    public function testPutStringBody(): void
    {
        $uri = 'http://httpbin.org/put';

        $body = 'zanzibar';
        $request = new Request($uri, "PUT");
        $request->setBody(BufferedContent::text($body));

        $response = $this->executeRequest($request);

        self::assertInstanceOf(Response::class, $response);

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertEquals($body, $result['data']);
    }

    /**
     * @dataProvider provideStatusCodes
     *
     * @throws \Throwable
     */
    public function testStatusCodeResponses($statusCode): void
    {
        $response = $this->executeRequest(new Request("http://httpbin.org/status/{$statusCode}"));

        self::assertInstanceOf(Response::class, $response);
        self::assertEquals($statusCode, $response->getStatus());
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

    public function testReason(): void
    {
        $response = $this->executeRequest(new Request("http://httpbin.org/status/418"));

        self::assertInstanceOf(Response::class, $response);

        $expectedReason = "I'M A TEAPOT";
        $actualReason = $response->getReason();

        self::assertSame($expectedReason, $actualReason);
    }

    public function testHttp2TeHeader(): void
    {
        $this->client = $this->builder->followRedirects(0)->build();

        $request = new Request('https://http2.pro/api/v1', 'POST');
        $request->setBody(BufferedContent::text('foobar'));
        $request->setProtocolVersions(['2']);
        $request->setHeader('te', 'gzip');

        $response = $this->executeRequest($request);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame($response, $response->getOriginalResponse());
    }

    public function testRedirect(): void
    {
        $this->client = $this->builder->followRedirects(0)->build();

        $this->givenRawServerResponse("HTTP/1.1 302 OK\r\nLocation: https://example.org/\r\n\r\n.");

        $response = $this->executeRequest($this->createRequest());

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->getStatus());
        self::assertSame($response, $response->getOriginalResponse());
    }

    public function testRedirectWithFollow(): void
    {
        $this->client = $this->builder->followRedirects()->build();

        $this->givenRawServerResponse("HTTP/1.1 302 OK\r\nLocation: https://example.org/\r\n\r\n.");

        $response = $this->executeRequest($this->createRequest());

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatus());
        self::assertSame(302, $response->getOriginalResponse()->getStatus());
    }

    public function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost(): void
    {
        $uri = 'http://httpbin.org/post';

        $request = new Request($uri);
        $request->setMethod('POST');

        $response = $this->executeRequest($request);

        self::assertInstanceOf(Response::class, $response);

        $body = $response->getBody()->buffer();
        $result = \json_decode($body, true);

        self::assertEquals('0', $result['headers']['Content-Length']);
    }

    public function testFormEncodedBodyRequest(): void
    {
        $body = new Form;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->add(FormField::text('field1', $field1));
        $body->add(FormField::text('field2', $field2));

        $request = new Request('http://httpbin.org/post', "POST");
        $request->setBody($body);

        $response = $this->executeRequest($request);

        self::assertInstanceOf(Response::class, $response);

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertEquals($field1, $result['form']['field1']);
        self::assertEquals($field2, $result['form']['field2']);
        self::assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }

    public function testFileBodyRequest(): void
    {
        $uri = 'http://httpbin.org/post';

        $bodyPath = __DIR__ . '/fixture/answer.txt';
        $body = StreamedContent::file($bodyPath);

        $request = new Request($uri, "POST");
        $request->setBody($body);

        $response = $this->executeRequest($request);

        self::assertInstanceOf(Response::class, $response);

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertStringEqualsFile($bodyPath, $result['data']);
    }

    public function testMultipartBodyRequest(): void
    {
        $field1 = 'test val';
        $file1 = __DIR__ . '/fixture/lorem.txt';
        $file2 = __DIR__ . '/fixture/answer.txt';

        $boundary = 'AaB03x';

        $body = new Form($boundary);
        $body->add(FormField::text('field1', $field1));
        $body->add(FormField::stream('file1', \Amp\Http\Client\StreamedContent::file($file1), \basename($file1)));
        $body->add(FormField::stream('file2', \Amp\Http\Client\StreamedContent::file($file2), \basename($file2)));

        $request = new Request('http://httpbin.org/post', "POST");
        $request->setBody($body);

        $response = $this->executeRequest($request);

        self::assertEquals(200, $response->getStatus());

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertEquals($field1, $result['form']['field1']);
        self::assertStringEqualsFile($file1, $result['files']['file1']);
        self::assertStringEqualsFile($file2, $result['files']['file2']);
        self::assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }

    /**
     * @requires extension zlib
     */
    public function testGzipResponse(): void
    {
        $this->givenNetworkInterceptor(new DecompressResponse);

        $response = $this->executeRequest(new Request('http://httpbin.org/gzip'));

        self::assertEquals(200, $response->getStatus());

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertTrue($result['gzipped']);
        self::assertFalse($response->hasHeader('content-encoding'));
    }

    /**
     * @requires extension zlib
     */
    public function testDeflateResponse(): void
    {
        $this->givenNetworkInterceptor(new DecompressResponse);

        $response = $this->executeRequest(new Request('http://httpbin.org/deflate'));

        self::assertEquals(200, $response->getStatus());

        $result = \json_decode($response->getBody()->buffer(), true);

        self::assertTrue($result['deflated']);
    }

    public function testInfiniteRedirect(): void
    {
        $this->builder->followRedirects(10);

        $request = $this->createRequest();
        $request->setUri($request->getUri()->withPath('/redirect/11'));

        $this->givenRawServerResponse("HTTP/1.1 302 OK\r\nLocation: /redirect/11\r\n\r\n.");

        $this->expectException(TooManyRedirectsException::class);

        $this->executeRequest($request);
    }

    public function testRequestCancellation(): void
    {
        $this->givenSlowRawServerResponse(
            0.1,
            "HTTP/1.1 200 OK\r\nConnection: close\r\nContent-Length: 1000\r\n\r\n",
            ...\array_fill(0, 1000, '.')
        );

        $cancellationTokenSource = new DeferredCancellation;
        $response = $this->executeRequest($this->createRequest(), $cancellationTokenSource->getCancellation());
        $cancellationTokenSource->cancel();
        $this->expectException(CancelledException::class);

        $response->getBody()->buffer();
    }

    public function testContentLengthBodyMismatchWithTooManyBytesSimple(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Body contained more bytes than specified in Content-Length, aborting request");

        $request = new Request("http://httpbin.org/post", "POST");
        $request->setBody(StreamedContent::text(new ReadableBuffer("foo"), 1));

        $this->executeRequest($request);
    }

    public function testContentLengthBodyMismatchWithTooManyBytesWith3ByteChunksAndLength2(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Body contained more bytes than specified in Content-Length, aborting request");

        $request = new Request("http://httpbin.org/post", "POST");
        $request->setBody(StreamedContent::text(new ReadableIterableStream(
            Pipeline::fromIterable(["a", "b", "c"])->delay(0.5)
        ), 2));

        $this->executeRequest($request);
    }

    public function testContentLengthBodyMismatchWithTooFewBytes(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Body contained fewer bytes than specified in Content-Length, aborting request");

        $request = new Request("http://httpbin.org/post", "POST");
        $request->setBody(StreamedContent::text(new ReadableBuffer("foo"), 42));

        $this->executeRequest($request);
    }

    public function testHttp2Support(): void
    {
        $response = $this->client->request(new Request('https://http2.pro/api/v1'));
        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        self::assertSame(1, $json['http2']);
        self::assertSame('HTTP/2.0', $json['protocol']);
        self::assertSame(1, $json['push']);
        self::assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2SupportBody(): void
    {
        $request = new Request('https://http2.pro/api/v1', 'POST');
        $request->setBody(BufferedContent::text('foobar'));

        $response = $this->client->request($request);
        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        self::assertSame(1, $json['http2']);
        self::assertSame('HTTP/2.0', $json['protocol']);
        self::assertSame(1, $json['push']);
        self::assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2SupportLargeBody(): void
    {
        $request = new Request('https://http2.pro/api/v1', 'POST');
        $request->setBody(BufferedContent::text(\str_repeat(',', 256 * 1024))); // larger than initial stream window

        $response = $this->client->request($request);
        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        self::assertSame(1, $json['http2']);
        self::assertSame('HTTP/2.0', $json['protocol']);
        self::assertSame(1, $json['push']);
        self::assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2SupportLargeResponseBody(): void
    {
        $request = new Request('https://1906714720.rsc.cdn77.org/img/cdn77-test-3mb.jpg', 'GET');
        $request->setTransferTimeout(100);
        $request->setBodySizeLimit(10000000000);

        $response = $this->client->request($request);
        $response->getBody()->buffer();

        self::assertSame(200, $response->getStatus());
    }

    public function testConcurrentSlowNetworkInterceptor(): void
    {
        $this->givenNetworkInterceptor(new ModifyRequest(static function (Request $request) {
            delay(2);
            return $request;
        }));

        [$response1, $response2] = Future\await([
            async(fn () => $this->client->request(new Request('https://http2.pro/api/v1'))),
            async(fn () => $this->client->request(new Request('https://http2.pro/api/v1'))),
        ]);

        $body1 = $response1->getBody()->buffer();
        $body2 = $response2->getBody()->buffer();

        $json1 = \json_decode($body1, true);
        $json2 = \json_decode($body2, true);

        self::assertSame(1, $json1['http2']);
        self::assertSame(1, $json2['http2']);
    }

    public function testHttp2UpgradeResponse(): void
    {
        $request = new Request('http://nghttp2.org/');
        $request->setHeader('connection', 'upgrade, http2-settings');
        $request->setHeader('upgrade', 'h2c');
        $request->setHeader('http2-settings', 'AAMAAABkAARAAAAAAAIAAAAA');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('CONNECT or upgrade request made without upgrade handler callback');

        $this->executeRequest($request);
    }

    public function testHttp2PriorKnowledge(): void
    {
        $request = new Request('http://nghttp2.org/');
        $request->setProtocolVersions(['2']);

        $response = $this->executeRequest($request);

        self::assertSame(200, $response->getStatus());
        self::assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2PriorKnowledgeUnsupported(): void
    {
        $request = new Request('http://stackoverflow.com/');
        $request->setProtocolVersions(['2']);

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Connection closed before HTTP/2 settings could be received');

        try {
            $this->executeRequest($request);
        } catch (UnprocessedRequestException $e) {
            throw $e->getPrevious();
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if (!isset($this->httpServer)) {
            return;
        }

        $this->httpServer->stop();

        if (isset($this->rawHandler)) {
            EventLoop::cancel($this->rawHandler);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = (new HttpClientBuilder)->retry(0);
        $this->client = $this->builder->build();

        $this->socket = Socket\listen(new Socket\InternetAddress('127.0.0.1', 0));
        $this->socket->unreference();

        $this->rawHandler = EventLoop::onReadable($this->socket->getResource(), function () {
            $client = $this->socket->accept();

            if ($client === null) {
                return;
            }

            $client->unreference();

            if (!$this->responseCallback) {
                $this->fail("No response callback set");
            }

            ($this->responseCallback)($client);
        });
    }

    private function givenRawServerResponse(string $response): void
    {
        $this->responseCallback = static function ($socket) use ($response): void {
            $buffer = '';

            // Await request before sending response
            while (null !== $chunk = $socket->read()) {
                $buffer .= $chunk;

                if (\str_contains($buffer, "\r\n\r\n")) {
                    break;
                }
            }

            $socket->write($response);
        };
    }

    private function givenSlowRawServerResponse(float $delay, string ...$chunks): void
    {
        $this->responseCallback = static function (Socket\Socket $socket) use ($delay, $chunks): void {
            try {
                foreach ($chunks as $chunk) {
                    $socket->write($chunk);
                    delay($delay);
                }
            } catch (StreamException) {
                // ignore
            }
        };
    }

    private function executeRequest(Request $request, ?Cancellation $cancellationToken = null): Response
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
