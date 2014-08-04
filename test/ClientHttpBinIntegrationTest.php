<?php

namespace ArtaxTest;

use Artax\Client;
use Artax\Request;
use Artax\FormBody;
use Artax\ResourceBody;
use Alert\NativeReactor;

class ClientHttpBinIntegrationTest extends \PHPUnit_Framework_TestCase {
    /**
     * @return \Artax\Client
     */
    private function generateClient() {
        return new Client(new NativeReactor);
    }

    public function testDefaultUserAgentSent() {
        $uri = 'http://httpbin.org/user-agent';
        $client = $this->generateClient();

        $response = $client->request($uri)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $body = $response->getBody();
        $result = json_decode($body);
        $this->assertSame(Client::USER_AGENT, $result->{'user-agent'});
    }

    public function testCustomUserAgentSentIfAssigned() {
        $uri = 'http://httpbin.org/user-agent';
        $client = $this->generateClient();

        $customUserAgent = 'test-user-agent';
        $client->setOption(Client::OP_USER_AGENT, $customUserAgent);
        $response = $client->request($uri)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $body = $response->getBody();
        $result = json_decode($body);
        $this->assertSame($customUserAgent, $result->{'user-agent'});
    }

    public function testPostStringBody() {
        $uri = 'http://httpbin.org/post';
        $client = $this->generateClient();

        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($body);
        $response = $client->request($request)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $result = json_decode($response->getBody());
        $this->assertEquals($body, $result->data);
    }

    public function testPostResourceBody() {
        $uri = 'http://httpbin.org/post';
        $client = $this->generateClient();

        $bodyString = 'zanzibar';
        $bodyStream = fopen('php://memory', 'r+');
        fwrite($bodyStream, $bodyString);
        rewind($bodyStream);
        $resourceBody = new ResourceBody($bodyStream);

        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($resourceBody);
        $response = $client->request($request)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $result = json_decode($response->getBody());
        $this->assertEquals($bodyString, $result->data);
    }

    public function testPutStringBody() {
        $uri = 'http://httpbin.org/put';
        $client = $this->generateClient();

        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('PUT')->setBody($body);
        $response = $client->request($request)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $result = json_decode($response->getBody());
        $this->assertEquals($body, $result->data);
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodeResponses($statusCode) {
        $uri = "http://httpbin.org/status/{$statusCode}";
        $client = $this->generateClient();

        $response = $client->request($uri)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
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
        $client = $this->generateClient();

        $response = $client->request($uri)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $expectedReason = "I'M A TEAPOT";
        $actualReason = $response->getReason();
        $this->assertSame($expectedReason, $actualReason);
    }

    public function testRedirect() {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url={$redirectTo}";
        $client = $this->generateClient();

        $response = $client->request($uri)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $this->assertEquals($statusCode, $response->getStatus());
    }

    public function testVerboseSend() {
        $uri = "http://httpbin.org/";
        $client = $this->generateClient();

        $client->setOption(Client::OP_VERBOSITY, Client::VERBOSE_SEND);
        $client->request($uri)->wait();

        $expectedOutput = '' .
            "GET / HTTP/1.1\r\n" .
            "Accept-Encoding: gzip, identity" . "\r\n" .
            "Host: httpbin.org" . "\r\n" .
            "User-Agent: " . Client::USER_AGENT . "\r\n" .
            "\r\n";

        $this->expectOutputString($expectedOutput);
    }

    public function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost() {
        $uri = 'http://httpbin.org/post';
        $client = $this->generateClient();

        $request = (new Request)->setUri($uri)->setMethod('POST');
        $response = $client->request($request)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $body = $response->getBody();
        $result = json_decode($body);
        $this->assertEquals('0', $result->headers->{'Content-Length'});
    }

    public function testFormEncodedBodyRequest() {
        $uri = 'http://httpbin.org/post';
        $client = $this->generateClient();

        $body = new FormBody;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);

        $request = (new Request)->setBody($body)->setUri($uri)->setMethod('POST');
        $response = $client->request($request)->wait();
        $this->assertInstanceOf('Artax\Response', $response);
        $result = json_decode($response->getBody(), true);
        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals($field2, $result['form']['field2']);
        $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }

    public function testMultipartBodyRequest() {
        $this->markTestSkipped("Don't think this one will work yet");

        // -----------------------------------------------------------------------------------------

        $client = new Client;
        $field1 = 'test val';
        $file1 = dirname(__DIR__) . '/fixture/lorem.txt';
        $file2 = dirname(__DIR__) . '/fixture/answer.txt';

        $boundary = 'AaB03x';

        $body = new FormBody($boundary);
        $body->addField('field1', $field1);
        $body->addFileField('file1', $file1);
        $body->addFileField('file2', $file2);

        $request = (new Request)->setBody($body)->setUri('http://httpbin.org/post')->setMethod('POST');
        $response = $client->request($request);
        $this->assertEquals(200, $response->getStatus());

        $result = json_decode($response->getBody(), true);

        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals(file_get_contents($file1), $result['files']['file1']);
        $this->assertEquals(file_get_contents($file2), $result['files']['file2']);
        $this->assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }

}
