<?php

namespace Amp\Artax\Test;

use Amp\Artax\BasicClient;
use Amp\Artax\FileBody;
use Amp\Artax\FormBody;
use Amp\Artax\InfiniteRedirectException;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;

class ClientHttpBinIntegrationTest extends TestCase {
    public function testDefaultUserAgentSent() {
        $uri = 'http://httpbin.org/user-agent';
        $client = new BasicClient;

        $promise = $client->request($uri);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody());
        $result = json_decode($body);

        $this->assertSame(BasicClient::DEFAULT_USER_AGENT, $result->{'user-agent'});
    }

    public function testCustomUserAgentSentIfAssigned() {
        $uri = 'http://httpbin.org/user-agent';
        $client = new BasicClient;

        $customUserAgent = 'test-user-agent';
        $request = (new Request($uri))->withHeader('User-Agent', $customUserAgent);

        $promise = $client->request($request);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody());
        $result = json_decode($body);

        $this->assertSame($customUserAgent, $result->{'user-agent'});
    }

    public function testPostStringBody() {
        $client = new BasicClient;

        $body = 'zanzibar';
        $request = (new Request('http://httpbin.org/post'))->withMethod('POST')->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = json_decode(wait($response->getBody()));

        $this->assertEquals($body, $result->data);
    }

    public function testPutStringBody() {
        $uri = 'http://httpbin.org/put';
        $client = new BasicClient;

        $body = 'zanzibar';
        $request = (new Request($uri, "PUT"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = json_decode(wait($response->getBody()));

        $this->assertEquals($body, $result->data);
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodeResponses($statusCode) {
        $client = new BasicClient;

        $response = wait($client->request("http://httpbin.org/status/{$statusCode}"));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($statusCode, $response->getStatus());
    }

    public function provideStatusCodes(): array {
        return [
            [200],
            [400],
            [404],
            [500],
        ];
    }

    public function testReason() {
        $client = new BasicClient;

        $response = wait($client->request("http://httpbin.org/status/418"));

        $this->assertInstanceOf(Response::class, $response);

        $expectedReason = "I'M A TEAPOT";
        $actualReason = $response->getReason();

        $this->assertSame($expectedReason, $actualReason);
    }

    public function testRedirect() {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url=" . \rawurlencode($redirectTo);

        $client = new BasicClient;

        $response = wait($client->request($uri));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($statusCode, $response->getStatus());

        $originalUri = $response->getOriginalRequest()->getUri();

        $this->assertSame($uri, $originalUri);
    }

    public function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost() {
        $uri = 'http://httpbin.org/post';
        $client = new BasicClient;

        $request = (new Request($uri))->withMethod('POST');

        $promise = $client->request($request);
        $response = wait($promise);

        $this->assertInstanceOf(Response::class, $response);

        $body = wait($response->getBody());
        $result = json_decode($body);

        $this->assertEquals('0', $result->headers->{'Content-Length'});
    }

    public function testFormEncodedBodyRequest() {
        $client = new BasicClient;

        $body = new FormBody;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);

        $request = (new Request('http://httpbin.org/post', "POST"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = json_decode(wait($response->getBody()), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals($field2, $result['form']['field2']);
        $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }

    public function testFileBodyRequest() {
        $uri = 'http://httpbin.org/post';
        $client = new BasicClient;

        $bodyPath = __DIR__ . '/fixture/answer.txt';
        $body = new FileBody($bodyPath);

        $request = (new Request($uri, "POST"))->withBody($body);
        $response = wait($client->request($request));

        $this->assertInstanceOf(Response::class, $response);

        $result = json_decode(wait($response->getBody()), true);

        $this->assertEquals(file_get_contents($bodyPath), $result['data']);
    }

    public function testMultipartBodyRequest() {
        $client = new BasicClient;

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

        $result = json_decode(wait($response->getBody()), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals(file_get_contents($file1), $result['files']['file1']);
        $this->assertEquals(file_get_contents($file2), $result['files']['file2']);
        $this->assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }

    public function testGzipResponse() {
        $client = new BasicClient;

        $response = wait($client->request('http://httpbin.org/gzip'));

        $this->assertEquals(200, $response->getStatus());

        $result = json_decode(wait($response->getBody()), true);

        $this->assertTrue($result['gzipped']);
    }

    public function testDeflateResponse() {
        $client = new BasicClient;

        $response = wait($client->request('http://httpbin.org/deflate'));

        $this->assertEquals(200, $response->getStatus());

        $result = json_decode(wait($response->getBody()), true);

        $this->assertTrue($result['deflated']);
    }

    public function testInfiniteRedirect() {
        $this->expectException(InfiniteRedirectException::class);

        wait((new BasicClient)->request("http://httpbin.org/redirect/11"));
    }

    public function testConnectionInfo() {
        /** @var Response $response */
        $response = wait((new BasicClient)->request("https://httpbin.org/get"));
        $this->assertContains(":", $response->getMetaInfo()->getConnectionInfo()->getLocalAddress());
        $this->assertContains(":", $response->getMetaInfo()->getConnectionInfo()->getRemoteAddress());
        $this->assertNotNull($response->getMetaInfo()->getConnectionInfo()->getTlsInfo());
        $this->assertSame("TLSv1.2", $response->getMetaInfo()->getConnectionInfo()->getTlsInfo()->getProtocol());
    }

    public function testRequestCancellation() {
        $cancellationTokenSource = new CancellationTokenSource;
        $response = wait((new BasicClient)->request("http://httpbin.org/drip?code=200&duration=5&numbytes=130000", [], $cancellationTokenSource->getToken()));
        $this->assertInstanceOf(Response::class, $response);
        $cancellationTokenSource->cancel();
        $this->expectException(CancelledException::class);
        wait($response->getBody());
    }
}
