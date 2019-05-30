<?php

namespace Amp\Artax\Test;

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Artax\FileBody;
use Amp\Artax\FormBody;
use Amp\Artax\HttpException;
use Amp\Artax\Request;
use Amp\Artax\RequestBody;
use Amp\Artax\Response;
use Amp\Artax\SocketException;
use Amp\Artax\TooManyRedirectsException;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Delayed;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Producer;
use Amp\Promise;
use Amp\Socket;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use function Amp\asyncCall;
use function Amp\Iterator\fromIterable;
use function Amp\Promise\wait;

class ClientHttpBinIntegrationTest extends TestCase
{
    /** @var Socket\Server */
    private $socket;
    private $server;

    public function testHttp10Response()
    {
        $client = new DefaultClient;
        $server = Socket\listen("tcp://127.0.0.1:0/");

        asyncCall(function () use ($server) {
            $client = yield $server->accept();
            yield $client->end("HTTP/1.0 200 OK\r\n\r\n");
        });

        $uri = "http://" . $server->getAddress();

        $promise = $client->request((new Request($uri))->withProtocolVersions(["1.0"]));
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame("", wait($response->getBody()->buffer()));
    }

    public function testCloseAfterConnect()
    {
        $client = new DefaultClient;
        $server = Socket\listen("tcp://127.0.0.1:0");

        asyncCall(function () use ($server) {
            while ($client = yield $server->accept()) {
                $client->close();
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $promise = $client->request((new Request($uri))->withProtocolVersions(["1.0"]));

            $this->expectException(SocketException::class);
            $this->expectExceptionMessage("Socket disconnected prior to response completion");

            wait($promise);
        } finally {
            $server->close();
        }
    }

    public function testIncompleteHttpResponseWithContentLength()
    {
        $client = new DefaultClient;
        $server = Socket\listen("tcp://127.0.0.1:0");

        asyncCall(function () use ($server) {
            while ($client = yield $server->accept()) {
                yield $client->end("HTTP/1.0 200 OK\r\nContent-Length: 2\r\n\r\n.");
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $promise = $client->request((new Request($uri))->withProtocolVersions(["1.0"]));

            $this->expectException(SocketException::class);
            $this->expectExceptionMessage("Socket disconnected prior to response completion (Parser state: 1)");

            $response = wait($promise);
            wait($response->getBody()->buffer());
        } finally {
            $server->close();
        }
    }

    public function testIncompleteHttpResponseWithChunkedEncoding()
    {
        $client = new DefaultClient;
        $server = Socket\listen("tcp://127.0.0.1:0");

        asyncCall(function () use ($server) {
            while ($client = yield $server->accept()) {
                yield $client->end("HTTP/1.0 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n0\r"); // missing \n
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $promise = $client->request((new Request($uri))->withProtocolVersions(["1.0"]));

            $this->expectException(SocketException::class);
            $this->expectExceptionMessage("Socket disconnected prior to response completion (Parser state: 3)");

            $response = wait($promise);
            wait($response->getBody()->buffer());
        } finally {
            $server->close();
        }
    }

    public function testIncompleteHttpResponseWithoutChunkedEncodingAndWithoutContentLength()
    {
        $client = new DefaultClient;
        $server = Socket\listen("tcp://127.0.0.1:0");

        asyncCall(function () use ($server) {
            while ($client = yield $server->accept()) {
                yield $client->end("HTTP/1.1 200 OK\r\n\r\n00000000000");
            }
        });

        try {
            $uri = "http://" . $server->getAddress() . "/";

            $promise = $client->request((new Request($uri))->withProtocolVersions(["1.0"]));

            $response = wait($promise);
            $this->assertSame("00000000000", wait($response->getBody()->buffer()));
        } finally {
            $server->close();
        }
    }

    public function testDefaultUserAgentSent()
    {
        $uri = 'http://httpbin.org/user-agent';
        $client = new DefaultClient;

        $promise = $client->request($uri);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody()->buffer());
        $result = \json_decode($body);

        $this->assertSame(DefaultClient::DEFAULT_USER_AGENT, $result->{'user-agent'});
    }

    public function testCustomUserAgentSentIfAssigned()
    {
        $uri = 'http://httpbin.org/user-agent';
        $client = new DefaultClient;

        $customUserAgent = 'test-user-agent';
        $request = (new Request($uri))->withHeader('User-Agent', $customUserAgent);

        $promise = $client->request($request);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody()->buffer());
        $result = \json_decode($body);

        $this->assertSame($customUserAgent, $result->{'user-agent'});
    }

    public function testCustomUserAgentSentIfAssignedViaDefaultHeaders()
    {
        $customUserAgent = 'test-user-agent';
        $uri = 'http://httpbin.org/user-agent';
        $client = new DefaultClient;
        $client->setOption(Client::OP_DEFAULT_HEADERS, [
            "user-agent" => $customUserAgent,
        ]);

        $promise = $client->request($uri);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody()->buffer());
        $result = \json_decode($body);

        $this->assertSame($customUserAgent, $result->{'user-agent'});
    }

    public function testPostStringBody()
    {
        $client = new DefaultClient;

        $body = 'zanzibar';
        $request = (new Request('http://httpbin.org/post'))->withMethod('POST')->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()));

        $this->assertEquals($body, $result->data);
    }

    public function testPutStringBody()
    {
        $uri = 'http://httpbin.org/put';
        $client = new DefaultClient;

        $body = 'zanzibar';
        $request = (new Request($uri, "PUT"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()));

        $this->assertEquals($body, $result->data);
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodeResponses($statusCode)
    {
        $client = new DefaultClient;

        $response = wait($client->request("http://httpbin.org/status/{$statusCode}"));

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

    public function testReason()
    {
        $client = new DefaultClient;

        $response = wait($client->request("http://httpbin.org/status/418"));

        $this->assertInstanceOf(Response::class, $response);

        $expectedReason = "I'M A TEAPOT";
        $actualReason = $response->getReason();

        $this->assertSame($expectedReason, $actualReason);
    }

    public function testRedirect()
    {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url=" . \rawurlencode($redirectTo);

        $client = new DefaultClient;

        $response = wait($client->request($uri));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($statusCode, $response->getStatus());

        $originalUri = $response->getOriginalRequest()->getUri();

        $this->assertSame($uri, $originalUri);
    }

    public function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost()
    {
        $uri = 'http://httpbin.org/post';
        $client = new DefaultClient;

        $request = (new Request($uri))->withMethod('POST');

        $promise = $client->request($request);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody()->buffer());
        $result = \json_decode($body);

        $this->assertEquals('0', $result->headers->{'Content-Length'});
    }

    public function testFormEncodedBodyRequest()
    {
        $client = new DefaultClient;

        $body = new FormBody;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);

        $request = (new Request('http://httpbin.org/post', "POST"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals($field2, $result['form']['field2']);
        $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }

    public function testFileBodyRequest()
    {
        $uri = 'http://httpbin.org/post';
        $client = new DefaultClient;

        $bodyPath = __DIR__ . '/fixture/answer.txt';
        $body = new FileBody($bodyPath);

        $request = (new Request($uri, "POST"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals(\file_get_contents($bodyPath), $result['data']);
    }

    public function testMultipartBodyRequest()
    {
        $client = new DefaultClient;

        $field1 = 'test val';
        $file1 = __DIR__ . '/fixture/lorem.txt';
        $file2 = __DIR__ . '/fixture/answer.txt';

        $boundary = 'AaB03x';

        $body = new FormBody($boundary);
        $body->addFields(['field1' => $field1]);
        $body->addFiles(['file1' => $file1, 'file2' => $file2]);

        $request = (new Request('http://httpbin.org/post', "POST"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertEquals(200, $response->getStatus());

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals(\file_get_contents($file1), $result['files']['file1']);
        $this->assertEquals(\file_get_contents($file2), $result['files']['file2']);
        $this->assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }

    /**
     * @requires extension zlib
     */
    public function testGzipResponse()
    {
        $client = new DefaultClient;

        $response = wait($client->request('http://httpbin.org/gzip'));

        $this->assertEquals(200, $response->getStatus());

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertTrue($result['gzipped']);
    }

    /**
     * @requires extension zlib
     */
    public function testDeflateResponse()
    {
        $client = new DefaultClient;

        $response = wait($client->request('http://httpbin.org/deflate'));

        $this->assertEquals(200, $response->getStatus());

        $result = \json_decode(wait($response->getBody()->buffer()), true);

        $this->assertTrue($result['deflated']);
    }

    public function testInfiniteRedirect()
    {
        $this->expectException(TooManyRedirectsException::class);

        wait((new DefaultClient)->request("http://httpbin.org/redirect/11"));
    }

    public function testConnectionInfo()
    {
        /** @var Response $response */
        $response = wait((new DefaultClient)->request("https://httpbin.org/get"));
        $connectionInfo = $response->getMetaInfo()->getConnectionInfo();

        $this->assertStringContainsString(":", $connectionInfo->getLocalAddress());
        $this->assertStringContainsString(":", $connectionInfo->getRemoteAddress());
        $this->assertNotNull($connectionInfo->getTlsInfo());
        $this->assertSame("TLSv1.2", $connectionInfo->getTlsInfo()->getProtocol());
        $this->assertNotEmpty($connectionInfo->getTlsInfo()->getPeerCertificates());
        $this->assertContains("httpbin.org", $connectionInfo->getTlsInfo()->getPeerCertificates()[0]->getNames());

        foreach ($connectionInfo->getTlsInfo()->getPeerCertificates() as $certificate) {
            $this->assertGreaterThanOrEqual($certificate->getValidFrom(), \time(), "Failed for " . $certificate->getSubject()->getCommonName());
            $this->assertLessThanOrEqual($certificate->getValidTo(), \time(), "Failed for " . $certificate->getSubject()->getCommonName());
        }
    }

    public function testRequestCancellation()
    {
        $cancellationTokenSource = new CancellationTokenSource;
        $response = wait((new DefaultClient)->request("http://" . $this->socket->getAddress() . "/", [], $cancellationTokenSource->getToken()));
        $this->assertInstanceOf(Response::class, $response);
        $cancellationTokenSource->cancel();
        $this->expectException(CancelledException::class);
        wait($response->getBody()->buffer());
    }

    public function testContentLengthBodyMismatchWithTooManyBytesSimple()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Body contained more bytes than specified in Content-Length, aborting request");

        $request = (new Request("http://httpbin.org/post", "POST"))
            ->withBody(new class implements RequestBody {
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

        wait((new DefaultClient)->request($request));
    }

    public function testContentLengthBodyMismatchWithTooManyBytesWith3ByteChunksAndLength2()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Body contained more bytes than specified in Content-Length, aborting request");

        $request = (new Request("http://httpbin.org/post", "POST"))
            ->withBody(new class implements RequestBody {
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

        wait((new DefaultClient)->request($request));
    }

    public function testContentLengthBodyMismatchWithTooFewBytes()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("Body contained fewer bytes than specified in Content-Length, aborting request");

        $request = (new Request("http://httpbin.org/post", "POST"))
            ->withBody(new class implements RequestBody {
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

        wait((new DefaultClient)->request($request));
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->socket) {
            $this->socket->close();
        }

        $this->socket = Socket\listen('127.0.0.1:0');
        $this->server = new Server([$this->socket], new CallableRequestHandler(static function () {
            return new \Amp\Http\Server\Response(Status::OK, [], new IteratorStream(new Producer(static function ($emit) {
                yield $emit(".");
                yield new Delayed(5000);
                yield $emit(".");
            })));
        }), new NullLogger);

        wait($this->server->start());
    }
}
