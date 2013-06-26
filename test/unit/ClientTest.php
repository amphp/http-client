<?php

use Artax\Client;

class ClientTest extends PHPUnit_Framework_TestCase {
    
    function testOptionSetters() {
        
        $client = new Client;
        $client->setAllOptions([
            'useKeepAlive'          => TRUE,
            'connectTimeout'        => 15,
            'transferTimeout'       => 30,
            'keepAliveTimeout'      => 30,
            'followLocation'        => TRUE,
            'autoReferer'           => TRUE,
            'maxConnections'        => -1,
            'maxConnectionsPerHost' => 4,
            'continueDelay'         => 3,
            'bufferBody'            => TRUE,
            'bindToIp'              => NULL,
            'ioGranularity'         => 65536,
            'verboseRead'           => FALSE,
            'verboseSend'           => FALSE
        ]);
        
    }
    
    
}
