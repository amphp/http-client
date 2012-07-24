<?php

use Artax\Http\Url,
    Artax\Http\StdRequest,
    Artax\Http\Client;

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
}
