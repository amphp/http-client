<?php

use Artax\Client;

class ClientTest extends PHPUnit_Framework_TestCase {

    function testOptionSetters() {
        $client = new Client;
        $client->setAllOptions([
            'useKeepAlive'          => TRUE,
            'connectTimeout'        => 15,
            'transferTimeout'       => 30,
            'keepaliveTimeout'      => 30,
            'followLocation'        => TRUE,
            'autoReferer'           => TRUE,
            'maxConnections'        => -1,
            'maxConnectionsPerHost' => 4,
            'continueDelay'         => 3,
            'bufferBody'            => TRUE,
            'maxHeaderBytes'        => 8192,
            'maxBodyBytes'          => -1,
            'bodySwapSize'          => 0,
            'storeBody'             => TRUE,
            'bindToIp'              => NULL,
            'ioGranularity'         => 65535,
            'autoEncoding'          => TRUE,
            'verboseRead'           => FALSE,
            'verboseSend'           => FALSE,
            'tlsOptions'            => []
        ]);
    }

    /**
     * @expectedException DomainException
     */
    function testOptionSettersThrowOnUnknownKey() {
        $client = new Client;
        $client->setOption('someInvalidKey', 42);
    }

}
