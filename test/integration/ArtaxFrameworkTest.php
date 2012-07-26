<?php

use Artax\Http\Url,
    Artax\Http\StdRequest,
    Artax\Http\Client,
    Artax\Http\StatusCodes;

class ArtaxFrameworkTest extends PHPUnit_Framework_TestCase {
    
    protected $client;
    
    public function setUp() {
        $this->client = $this->client ?: new Client();
    }
    
    public function testOkayResponse() {
        $request = new StdRequest('http://localhost:8096', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Index::get', $response->getBody());
    }
    
    public function testPostResponse() {
        $headers = array('User-Agent' => 'IntegrationTest');
        $body = urlencode('var1=test1&var2=test2');
        
        $request = new StdRequest(
            'http://localhost:8096/post-only',
            '1.1',
            'POST',
            $headers,
            $body
        );
        
        $response = $this->client->send($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("PostOnly::post - $body", $response->getBody());
    }
    
    public function testPostWithRedirect() {
        $this->markTestSkipped('Relevent Client redirect code not yet implemented');
        
        $headers = array('User-Agent' => 'IntegrationTest');
        $body = urlencode('var1=test1&var2=test2');
        
        $request = new StdRequest(
            'http://localhost:8096/post-redir',
            '1.1',
            'POST',
            $headers,
            $body
        );
        
        $response = $this->client->send($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("PostOnly::post - $body", $response->getBody());
    }
    
    public function testNotFoundResponse() {
        $request = new StdRequest('http://localhost:8096/nonexistent', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('not found', $response->getBody());
    }
    
    public function testMethodNotAllowedResponse() {
        $request = new StdRequest('http://localhost:8096/post-only', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('method not allowed', $response->getBody());
    }
    
    public function testErrorResponse() {
        $request = new StdRequest('http://localhost:8096/error', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('error', $response->getBody());
    }
    
    public function testExceptionResponse() {
        $request = new StdRequest('http://localhost:8096/exception', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('exception', $response->getBody());
    }
    
    public function testFatalResponse() {
        $request = new StdRequest('http://localhost:8096/fatal-error', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('fatal', $response->getBody());
    }
    
    public function testExceptionResponseOnIllegalSystemEventDelta() {
        $request = new StdRequest('http://localhost:8096/sysevent', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('illegal sysevent delta', $response->getBody());
    }
    
    public function testAutoStatusPluginIntegration() {
        $request = new StdRequest('http://localhost:8096/auto-status', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(StatusCodes::HTTP_200, $response->getStatusDescription());
    }
    
    public function testAutoContentLengthPluginIntegration() {
        $request = new StdRequest('http://localhost:8096/auto-length', '1.1', 'GET', array(
            'User-Agent' => 'IntegrationTest'
        ));
        
        $response = $this->client->send($request);
        
        $this->assertTrue($response->hasHeader('Content-Length'));
        $this->assertEquals(strlen($response->getBody()), $response->getHeader('Content-Length'));
    }
}
