<?php

namespace Amp\ArtaxTest;

use Amp\Artax\Client;
use Amp\Artax\Request;
use Amp\Artax\FileBody;
use Amp\Artax\FormBody;
use Amp\NativeReactor;

\Amp\reactor(new NativeReactor());

class ClientHttpBinIntegrationTest extends \PHPUnit_Framework_TestCase {
    public function testDefaultUserAgentSent() {
        $uri = 'http://httpbin.org/user-agent';
        $client = new Client;

        $promise = $client->request($uri);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $body = $response->getBody();
        $result = json_decode($body);
        $this->assertSame(Client::USER_AGENT, $result->{'user-agent'});
    }

    public function testCustomUserAgentSentIfAssigned() {
        $uri = 'http://httpbin.org/user-agent';
        $client = new Client;

        $customUserAgent = 'test-user-agent';
        $request = (new Request)->setUri($uri)->setHeader('User-Agent', $customUserAgent);
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $body = $response->getBody();
        $result = json_decode($body);
        $this->assertSame($customUserAgent, $result->{'user-agent'});
    }

    public function testPostStringBody() {
        $uri = 'http://httpbin.org/post';
        $client = new Client;

        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($body);
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $result = json_decode($response->getBody());
        $this->assertEquals($body, $result->data);
    }

    public function testPutStringBody() {
        $uri = 'http://httpbin.org/put';
        $client = new Client;

        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('PUT')->setBody($body);
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $result = json_decode($response->getBody());
        $this->assertEquals($body, $result->data);
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodeResponses($statusCode) {
        $uri = "http://httpbin.org/status/{$statusCode}";
        $client = new Client;

        $promise = $client->request($uri);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $this->assertEquals($statusCode, $response->getStatus());
    }

    public function provideStatusCodes() {
        return array(
            array(200),
            array(400),
            array(404),
            array(500)
        );
    }

    public function testReason() {
        $uri = "http://httpbin.org/status/418";
        $client = new Client;

        $promise = $client->request($uri);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $expectedReason = "I'M A TEAPOT";
        $actualReason = $response->getReason();
        $this->assertSame($expectedReason, $actualReason);
    }

    public function testRedirect() {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url={$redirectTo}";
        $client = new Client;

        $promise = $client->request($uri);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $this->assertEquals($statusCode, $response->getStatus());
        $originalUri = $response->getOriginalRequest()->getUri();
        $this->assertSame($uri, urldecode($originalUri));
    }

    public function testVerboseSend() {
        $uri = "http://httpbin.org/";
        $client = new Client;

        $client->setOption(Client::OP_VERBOSITY, Client::VERBOSE_SEND);
        $promise = $client->request($uri);
        $response = \Amp\wait($promise);

        $expectedLines = [
            "GET / HTTP/1.1",
            "Accept-Encoding: gzip, identity",
            "Host: httpbin.org",
            "User-Agent: " . Client::USER_AGENT,
            "Accept: */*",
        ];

        $this->expectOutputString(implode("\r\n", $expectedLines) . "\r\n\r\n");
    }

    public function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost() {
        $uri = 'http://httpbin.org/post';
        $client = new Client;

        $request = (new Request)->setUri($uri)->setMethod('POST');
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $body = $response->getBody();
        $result = json_decode($body);
        $this->assertEquals('0', $result->headers->{'Content-Length'});
    }

    public function testFormEncodedBodyRequest() {
        //$this->markTestSkipped();
        $uri = 'http://httpbin.org/post';
        $client = new Client;

        $body = new FormBody;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);

        $request = (new Request)->setBody($body)->setUri($uri)->setMethod('POST');
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $result = json_decode($response->getBody(), true);
        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals($field2, $result['form']['field2']);
        $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }

    public function testFileBodyRequest() {
        $uri = 'http://httpbin.org/post';
        $client = new Client;

        $bodyPath = __DIR__ . '/fixture/answer.txt';
        $body = new FileBody($bodyPath);
        $request = (new Request)->setBody($body)->setUri($uri)->setMethod('POST');
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertInstanceOf('Amp\Artax\Response', $response);
        $result = json_decode($response->getBody(), true);
        $this->assertEquals(file_get_contents($bodyPath), $result['data']);
    }

    public function testMultipartBodyRequest() {
        $uri = 'http://httpbin.org/post';
        $client = new Client;
        $field1 = 'test val';
        $file1 = __DIR__ . '/fixture/lorem.txt';
        $file2 = __DIR__ . '/fixture/answer.txt';

        $boundary = 'AaB03x';

        $body = new FormBody($boundary);
        $body->addField('field1', $field1);
        $body->addFile('file1', $file1);
        $body->addFile('file2', $file2);

        $request = (new Request)->setBody($body)->setUri('http://httpbin.org/post')->setMethod('POST');
        $promise = $client->request($request);
        $response = \Amp\wait($promise);
        $this->assertEquals(200, $response->getStatus());

        $result = json_decode($response->getBody(), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals(file_get_contents($file1), $result['files']['file1']);
        $this->assertEquals(file_get_contents($file2), $result['files']['file2']);
        $this->assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }
}
