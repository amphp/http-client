<?php

use Artax\Http\RequestDetector,
    Artax\Url;

class RequestDetectorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\RequestDetector::__construct
     * @covers Artax\Http\RequestDetector::detectUrl
     */
    public function testDetectUrlUsesComposedUrlTranslatorToMake() {
        $url = new Url('http', 'localhost');
        
        $mock = $this->getMock('Artax\\SuperglobalToUrlTranslator', array('make'));
        $mock->expects($this->once())
             ->method('make')
             ->will($this->returnValue($url));
        
        $detector = new RequestDetector($mock);
        $this->assertEquals($url, $detector->detectUrl(array()));
    }
    
    /**
     * @covers Artax\Http\RequestDetector::__construct
     * @covers Artax\Http\RequestDetector::detectHeaders
     */
    public function testDetectHeadersUsesNativeFunctionIfAvailable() {
        $mock = $this->getMock('Artax\Http\RequestDetector', array('detectHeadersNatively'));
        $headers = array('mock', 'header', 'list');
        $mock->expects($this->once())
             ->method('detectHeadersNatively')
             ->will($this->returnValue($headers));
        $this->assertEquals($headers, $mock->detectHeaders($headers));
    }
    
    public function provideServerSuperglobal() {
        return array(array(
            array(
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
            )
        ));
    }
    
    /**
     * @dataProvider provideServerSuperglobal
     * @covers Artax\Http\RequestDetector::__construct
     * @covers Artax\Http\RequestDetector::detectHeaders
     * @covers Artax\Http\RequestDetector::detectHeadersNatively
     */
    public function testDetectHeadersParsesServerArrayIfNativeFunctionUnavailable($_server) {
        $detector = new RequestDetector;
        $headers = $detector->detectHeaders($_server);
    }
    
    /**
     * @dataProvider provideServerSuperglobal
     * @covers Artax\Http\RequestDetector::__construct
     * @covers Artax\Http\RequestDetector::detectMethod
     */
    public function testDetectMethodParsesRelevantSuperglobalEntry($_server) {
        $detector = new RequestDetector;
        $method = $detector->detectMethod($_server);
        $this->assertEquals($_server['REQUEST_METHOD'], $method);
    }
    
    /**
     * @dataProvider provideServerSuperglobal
     * @covers Artax\Http\RequestDetector::__construct
     * @covers Artax\Http\RequestDetector::detectHttpVersion
     */
    public function testDetectHttpVersionParsesRelevantSuperglobalEntry($_server) {
        $detector = new RequestDetector;
        $_server['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $version = $detector->detectHttpVersion($_server);
        $this->assertEquals('1.1', $version);
    }
    
    /**
     * @covers Artax\Http\RequestDetector::__construct
     * @covers Artax\Http\RequestDetector::detectBody
     */
    public function testDetectBody() {
        $detector = new RequestDetector;
        $this->assertEquals('', $detector->detectBody());
    }
    
}
