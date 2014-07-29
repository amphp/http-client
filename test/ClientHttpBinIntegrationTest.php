<?php

namespace Artax\Test;

use Artax\Client,
    Artax\Request,
    Artax\FormBody,
    Artax\ResourceBody,
    Alert\NativeReactor;

class ClientHttpBinIntegrationTest extends \PHPUnit_Framework_TestCase {
    private function generateClientAndReactor() {
        $reactor = new NativeReactor;
        $client = new Client($reactor);
        return [$client, $reactor];
    }

    public function testDefaultUserAgentSent() {
        $uri = 'http://httpbin.org/user-agent';
        list($client, $reactor) = $this->generateClientAndReactor();

        $promise = $client->request($uri);
        $promise->onResolve(function($error, $response) use ($reactor) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $body = $response->getBody();
            $result = json_decode($body);
            $this->assertSame(Client::USER_AGENT, $result->{'user-agent'});
        });

        $reactor->run();
    }

    public function testCustomUserAgentSentIfAssigned() {
        $uri = 'http://httpbin.org/user-agent';
        list($client, $reactor) = $this->generateClientAndReactor();

        $customUserAgent = 'test-user-agent';
        $client->setOption(Client::OP_USER_AGENT, $customUserAgent);
        $promise = $client->request($uri);

        $promise->onResolve(function($error, $response) use ($reactor, $customUserAgent) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $body = $response->getBody();
            $result = json_decode($body);
            $this->assertSame($customUserAgent, $result->{'user-agent'});
        });

        $reactor->run();
    }

    public function testPostStringBody() {
        $uri = 'http://httpbin.org/post';
        list($client, $reactor) = $this->generateClientAndReactor();

        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($body);
        $promise = $client->request($request);
        $promise->onResolve(function($error, $response) use ($reactor, $body) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $result = json_decode($response->getBody());
            $this->assertEquals($body, $result->data);
        });

        $reactor->run();
    }

    public function testPostResourceBody() {
        $uri = 'http://httpbin.org/post';
        list($client, $reactor) = $this->generateClientAndReactor();

        $bodyString = 'zanzibar';
        $bodyStream = fopen('php://memory', 'r+');
        fwrite($bodyStream, $bodyString);
        rewind($bodyStream);
        $resourceBody = new ResourceBody($bodyStream);

        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($resourceBody);
        $promise = $client->request($request);
        $promise->onResolve(function($error, $response) use ($reactor, $bodyString) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $result = json_decode($response->getBody());
            $this->assertEquals($bodyString, $result->data);
        });

        $reactor->run();
    }

    public function testPutStringBody() {
        $uri = 'http://httpbin.org/put';
        list($client, $reactor) = $this->generateClientAndReactor();

        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('PUT')->setBody($body);
        $promise = $client->request($request);
        $promise->onResolve(function($error, $response) use ($reactor, $body) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $result = json_decode($response->getBody());
            $this->assertEquals($body, $result->data);
        });

        $reactor->run();
    }

    /**
     * @dataProvider provideStatusCodes
     */
    public function testStatusCodeResponses($statusCode) {
        $uri = "http://httpbin.org/status/{$statusCode}";
        list($client, $reactor) = $this->generateClientAndReactor();

        $promise = $client->request($uri);
        $promise->onResolve(function($error, $response) use ($reactor, $statusCode) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $this->assertEquals($statusCode, $response->getStatus());
        });

        $reactor->run();
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
        list($client, $reactor) = $this->generateClientAndReactor();

        $promise = $client->request($uri);
        $promise->onResolve(function($error, $response) use ($reactor) {
            $reactor->stop();
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $expectedReason = "I'M A TEAPOT";
            $actualReason = $response->getReason();
            $this->assertEquals($expectedReason, $actualReason);
        });

        $reactor->run();
    }

    public function testRedirect() {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url={$redirectTo}";
        list($client, $reactor) = $this->generateClientAndReactor();

        $promise = $client->request($uri);
        $promise->onResolve(function($error, $response) use ($reactor, $statusCode) {
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $this->assertEquals($statusCode, $response->getStatus());
            $reactor->stop();
        });

        $reactor->run();
    }

    public function testVerboseSend() {
        $uri = "http://httpbin.org/";
        list($client, $reactor) = $this->generateClientAndReactor();

        $client->setOption(Client::OP_VERBOSITY, Client::VERBOSE_SEND);
        $promise = $client->request($uri);
        $promise->onResolve(function() use ($reactor) {
            $reactor->stop();
        });

        $reactor->run();

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
        list($client, $reactor) = $this->generateClientAndReactor();

        $request = (new Request)->setUri($uri)->setMethod('POST');
        $promise = $client->request($request);
        $promise->onResolve(function($error, $response) use ($reactor) {
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $body = $response->getBody();
            $result = json_decode($body);
            $this->assertEquals('0', $result->headers->{'Content-Length'});
            $reactor->stop();
        });

        $reactor->run();
    }

    public function testFormEncodedBodyRequest() {
        $uri = 'http://httpbin.org/post';
        list($client, $reactor) = $this->generateClientAndReactor();

        $body = new FormBody;
        $field1 = 'test val';
        $field2 = 'val2';
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);

        $request = (new Request)->setBody($body)->setUri($uri)->setMethod('POST');
        $promise = $client->request($request);
        $promise->onResolve(function($error, $response) use ($reactor, $field1, $field2) {
            $this->assertNull($error);
            $this->assertInstanceOf('Artax\Response', $response);
            $body = $response->getBody();
            $result = json_decode($response->getBody(), true);
            $this->assertEquals($field1, $result['form']['field1']);
            $this->assertEquals($field2, $result['form']['field2']);
            $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
            $reactor->stop();
        });

        $reactor->run();
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
