<?php

use Artax\Http\StdRequestFactory,
    Artax\Http\StdRequestDetector,
    Artax\Http\SuperglobalUriDetector;

class StdRequestFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdRequestFactory::__construct
     * @covers Artax\Http\StdRequestFactory::make
     */
    public function testMakeReturnsNewStdRequestInstance() {
        
        $_server = array(
            'DOCUMENT_ROOT' => '/home/daniel/dev',
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => '51248',
            'SERVER_SOFTWARE' => 'PHP 5.4.4 Development Server',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '8000',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'POST',
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => '/home/daniel/dev/index.php',
            'PHP_SELF' => '/index.php',
            'CONTENT-TYPE' => 'application/json',
            'CONTENT-LENGTH' => '42',
            'HTTP_HOST' => 'localhost:8000',
            'HTTP_CONNECTION' => 'keep-alive',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.15 Safari/537.1',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_ENCODING' => 'gzip,deflate,sdch',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'REQUEST_TIME_FLOAT' => '1342119105.835',
            'REQUEST_TIME' => '1342119105'
        );
        
        $factory = new StdRequestFactory(new StdRequestDetector(new SuperglobalUriDetector));
        $this->assertInstanceOf('Artax\\Http\\StdRequest', $factory->make($_server));
    }
    
}
