<?php

use Artax\Client,
    Artax\Request,
    Artax\FormBody,
    Artax\ResourceBody;

class ClientHttpBinIntegrationTest extends PHPUnit_Framework_TestCase {

    private $client;
    
    function setUp() {
        $this->client = new Client;
    }
    
    function tearDown() {
        $this->client = NULL;
    }
    
    function testUserAgentResponse() {
        $uri = 'http://httpbin.org/user-agent';
        $response = $this->client->request($uri);
        $body = $response->getBody();
        
        $result = json_decode($body);
        
        $this->assertEquals(Client::USER_AGENT, $result->{'user-agent'});
    }
    
    function testPostStringBody() {
        $uri = 'http://httpbin.org/post';
        
        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($body);
        $response = $this->client->request($request);
        $rcvdBody = $response->getBody();
        
        $result = json_decode($rcvdBody);
        
        $this->assertEquals($body, $result->data);
    }
    
    function testPostResourceBody() {
        $uri = 'http://httpbin.org/post';
        
        $body = 'zanzibar';
        $bodyStream = fopen('php://memory', 'r+');
        fwrite($bodyStream, $body);
        rewind($bodyStream);
        $resourceBody = new ResourceBody($bodyStream);
        
        $request = (new Request)->setUri($uri)->setMethod('POST')->setBody($resourceBody);
        $response = $this->client->request($request);
        $rcvdBody = $response->getBody();
        
        $result = json_decode($rcvdBody);
        
        $this->assertEquals($body, $result->data);
    }
    
    function testPutStringBody() {
        $uri = 'http://httpbin.org/put';
        
        $body = 'zanzibar';
        $request = (new Request)->setUri($uri)->setMethod('PUT')->setBody($body);
        $response = $this->client->request($request);
        $rcvdBody = $response->getBody();
        
        $result = json_decode($rcvdBody);
        
        $this->assertEquals($body, $result->data);
    }
    
    /**
     * @dataProvider provideStatusCodes
     */
    function testStatusCodeResponses($statusCode) {
        $uri = "http://httpbin.org/status/{$statusCode}";
        $response = $this->client->request($uri);
        $this->assertEquals($statusCode, $response->getStatus());
    }
    
    function provideStatusCodes() {
        return array(
            array(200),
            array(400),
            array(404),
            array(500)
        );
    }
    
    function testReason() {
        $uri = "http://httpbin.org/status/418";
        $response = $this->client->request($uri);
        $expected = "I'M A TEAPOT";
        $actual = $response->getReason();
        $this->assertEquals($expected, $actual);
    }
    
    function testRedirect() {
        $statusCode = 299;
        $redirectTo = "/status/{$statusCode}";
        $uri = "http://httpbin.org/redirect-to?url={$redirectTo}";
        $response = $this->client->request($uri);
        $this->assertEquals($statusCode, $response->getStatus());
    }
    
    function testVerboseSend() {
        $expectedOutput = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: " . Client::USER_AGENT . "\r\n" .
            "Host: httpbin.org\r\n" . 
            "Accept-Encoding: gzip, identity" . "\r\n\r\n";
        
        $this->expectOutputString($expectedOutput);
        
        $uri = "http://httpbin.org/";
        $this->client->setOption('verboseSend', TRUE);
        $response = $this->client->request($uri);
    }
    
    function testMultiRequest() {
        $uris = [
            'http://httpbin.org',
            'http://httpbin.org',
            'http://httpbin.org',
            'http://httpbin.org',
            'http://httpbin.org',
            'http://httpbin.org',
            'http://httpbin.org'
        ];
        
        $onResponse = function(){};
        $onError = function(){};
        
        $this->client->requestMulti($uris, $onResponse, $onError);
    }
    
    function testFormEncodedBodyRequest() {
        $field1 = 'test val';
        $field2 = 'val2';
        
        $body = new FormBody;
        $body->addField('field1', $field1);
        $body->addField('field2', $field2);
        
        $request = (new Request)->setBody($body)->setUri('http://httpbin.org/post')->setMethod('POST');
        $response = $this->client->request($request);
        $result = json_decode($response->getBody(), TRUE);
        
        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals($field2, $result['form']['field2']);
        $this->assertEquals('application/x-www-form-urlencoded', $result['headers']['Content-Type']);
    }
    
    function testMultipartBodyRequest() {
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
        
        $result = json_decode($response->getBody(), TRUE);
        
        $this->assertEquals($field1, $result['form']['field1']);
        $this->assertEquals(file_get_contents($file1), $result['files']['file1']);
        $this->assertEquals(file_get_contents($file2), $result['files']['file2']);
        $this->assertEquals('multipart/form-data; boundary=' . $boundary, $result['headers']['Content-Type']);
    }
    
    function testClientAddsZeroContentLengthHeaderForEmptyBodiesOnPost() {
        $uri = 'http://httpbin.org/post';
        
        $request = (new Request)->setUri($uri)->setMethod('POST');
        $response = $this->client->request($request);
        $rcvdBody = $response->getBody();
        
        $result = json_decode($rcvdBody);
        
        $this->assertEquals('0', $result->headers->{'Content-Length'});
    }
}

