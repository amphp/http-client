<?php

use Artax\Client,
    Artax\Ext\Progress\ProgressExtension;

class ExtProgressHttpBinIntegrationTest extends PHPUnit_Framework_TestCase {

    function testProgress() {
        $client = new Client;
        $ext = new ProgressExtension;
        $ext->extend($client);
        
        $progress;
        
        $ext->subscribe([
            ProgressExtension::PROGRESS => function($dataArr) use (&$progress) {
                $progress = $dataArr[1];
            }
        ]);
        
        $uri = 'http://httpbin.org/';
        $response = $client->request($uri);
        $this->assertEquals(200, $response->getStatus());
    }
    
}

