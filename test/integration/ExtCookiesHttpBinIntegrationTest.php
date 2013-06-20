<?php

use Artax\Client,
    Artax\Ext\Cookies\CookieExtension,
    Artax\Ext\Cookies\ArrayCookieJar,
    Artax\Ext\Cookies\FileCookieJar;

class ExtCookiesHttpBinIntegrationTest extends PHPUnit_Framework_TestCase {

    function testArrayCookieJar() {
        $client = new Client;
        $client->setOption('followLocation', FALSE);
        
        $cookieJar = new ArrayCookieJar();
        $ext = new CookieExtension($cookieJar);
        $ext->extend($client);
        
        $uri = 'http://httpbin.org/cookies/set?k1=v1&k2=v2';
        $response = $client->request($uri);
        $this->assertEquals(302, $response->getStatus());
        
        $uri = 'http://httpbin.org/cookies';
        $response = $client->request($uri);
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), TRUE);
        $this->assertEquals('v1', $body['cookies']['k1']);
        $this->assertEquals('v2,', $body['cookies']['k2']);
    }
    
    function testFileCookieJar() {
        $client = new Client;
        $client->setOption('followLocation', FALSE);
        
        $path = dirname(__DIR__) . '/fixture/cookies.tmp';
        $cookieJar = new FileCookieJar($path);
        $ext = new CookieExtension($cookieJar);
        $ext->extend($client);
        
        $uri = 'http://httpbin.org/cookies/set?k1=v1&k2=v2';
        $response = $client->request($uri);
        $this->assertEquals(302, $response->getStatus());
        
        $uri = 'http://httpbin.org/cookies';
        $response = $client->request($uri);
        $this->assertEquals(200, $response->getStatus());
        
        $body = json_decode($response->getBody(), TRUE);
        $this->assertEquals('v1', $body['cookies']['k1']);
        $this->assertEquals('v2,', $body['cookies']['k2']);
        
        $ext->unextend();
        unset($ext, $cookieJar);
        
        @unlink($path);
    }
    
}

