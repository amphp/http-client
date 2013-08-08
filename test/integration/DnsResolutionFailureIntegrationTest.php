<?php

use Artax\Client,
    Artax\AsyncClient,
    Alert\ReactorFactory;

class DnsResolutionFailureIntegrationTest extends PHPUnit_Framework_TestCase {

    private $client;
    
    function setUp() {
        $this->client = new Client;
    }
    
    function tearDown() {
        $this->client = NULL;
    }
    
    /**
     * @expectedException Artax\DnsException
     * @expectedExceptionMessage DNS resolution failed for ajfhfkhaflkhafhalfhjfasdhfklasdhsjafhkaslf.net
     */
    function testClientThrowsExceptionOnDnsResolutionFailure() {
        $client = new Client;
        $client->request('http://ajfhfkhaflkhafhalfhjfasdhfklasdhsjafhkaslf.net/');
    }
    
    function testAsyncClientNotifiesErrorCallbackOnDnsResolutionFailure() {
        $reactor = (new ReactorFactory)->select();
        $asyncClient = new AsyncClient($reactor);
        
        $increment = 0;
        $onResponse = function() use ($reactor) { $reactor->stop(); };
        $onError = function() use ($reactor, &$increment) { $increment++; $reactor->stop(); };
        
        $uri = 'http://ajfhfkhaflkhafhalfhjfasdhfklasdhsjafhkaslf.net/';
        
        $asyncClient->request($uri, $onResponse, $onError);
        $reactor->run();
        $this->assertEquals(1, $increment);
    }
    
}
