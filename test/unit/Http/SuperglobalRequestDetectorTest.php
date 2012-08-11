<?php

use Artax\Http\SuperglobalRequestDetector,
    Artax\Http\StdUri;

class SuperglobalRequestDetectorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\SuperglobalRequestDetector::detectHeaders
     */
    public function testDetectHeadersUsesNativeFunctionIfAvailable() {
        $mock = $this->getMock(
            'Artax\\Http\\SuperglobalRequestDetector',
            array('detectHeadersNatively')
        );
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
     * @covers Artax\Http\SuperglobalRequestDetector::detectHeaders
     * @covers Artax\Http\SuperglobalRequestDetector::detectHeadersNatively
     */
    public function testDetectHeadersParsesServerArrayIfNativeFunctionUnavailable($_server) {
        $detector = new SuperglobalRequestDetector(new SuperglobalUriDetector);
        $headers = $detector->detectHeaders($_server);
    }
    
    /**
     * @dataProvider provideServerSuperglobal
     * @covers Artax\Http\SuperglobalRequestDetector::detectMethod
     */
    public function testDetectMethodParsesRelevantSuperglobalEntry($_server) {
        $detector = new SuperglobalRequestDetector(new SuperglobalUriDetector);
        $method = $detector->detectMethod($_server);
        $this->assertEquals($_server['REQUEST_METHOD'], $method);
    }
    
    /**
     * @dataProvider provideServerSuperglobal
     * @covers Artax\Http\SuperglobalRequestDetector::detectHttpVersion
     */
    public function testDetectHttpVersionParsesRelevantSuperglobalEntry($_server) {
        $detector = new SuperglobalRequestDetector(new SuperglobalUriDetector);
        $_server['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $version = $detector->detectHttpVersion($_server);
        $this->assertEquals('1.1', $version);
    }
    
    /**
     * @covers Artax\Http\SuperglobalRequestDetector::detectBody
     */
    public function testDetectBody() {
        $detector = new SuperglobalRequestDetector(new SuperglobalUriDetector);
        $this->assertTrue(is_resource($detector->detectBody()));
    }
    
    public function provideServerSuperglobalsForUriParsing() {
        return array(
            array(array(
                'REQUEST_URI' => '/index.html',
                'HTTP_HOST' => 'localhost',
                'SERVER_NAME' => 'localhost',
                'expectedResult' => 'http://localhost/index.html',
                'expectedPort' => 80
            )),
            array(array(
                'REQUEST_URI' => '/index.html',
                'HTTP_HOST' => 'localhost',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'expectedResult' => 'http://localhost/index.html',
                'expectedPort' => 80
            )),
            array(array(
                'REQUEST_URI' => '/index.html',
                'HTTP_HOST' => 'localhost',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 443,
                'HTTPS' => true,
                'expectedResult' => 'https://localhost/index.html',
                'expectedPort' => 443
            )),
            array(array(
                'REQUEST_URI' => '/index.html',
                'HTTP_HOST' => 'localhost:80',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'HTTPS' => true,
                'expectedResult' => 'https://localhost:80/index.html',
                'expectedPort' => 80
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => 'localhost:8080',
                'SERVER_NAME' => 'localhost',
                'HTTPS' => 'off',
                'SERVER_PORT' => 8080,
                'expectedResult' => 'http://localhost:8080/redirect.php',
                'expectedPort' => 8080
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => '127.0.0.1:9999',
                'SERVER_NAME' => '127.0.0.1',
                'HTTPS' => 'off',
                'SERVER_PORT' => 8080,
                'expectedResult' => 'http://127.0.0.1:8080/redirect.php',
                'expectedPort' => 8080
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => '127.0.0.1',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => 80,
                'expectedResult' => 'http://127.0.0.1/redirect.php',
                'expectedPort' => 80
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => '127.0.0.1:8123',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => 8123,
                'expectedResult' => 'http://127.0.0.1:8123/redirect.php',
                'expectedPort' => 8123
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => 'dont-trust-me',
                'SERVER_NAME' => '127.0.0.1',
                'HTTPS' => 'off',
                'SERVER_PORT' => 80,
                'expectedResult' => 'http://127.0.0.1/redirect.php',
                'expectedPort' => 80
            )),
            array(array(
                'REQUEST_URI' => '/index.php?queryVar=test',
                'QUERY_STRING' => 'queryVar=test',
                'HTTP_HOST' => 'localhost',
                'SERVER_NAME' => 'localhost',
                'HTTPS' => true,
                'expectedResult' => 'https://localhost/index.php?queryVar=test',
                'expectedPort' => 443
            )),
            array(array(
                'REQUEST_URI' => 'http://localhost',
                'SERVER_NAME' => 'localhost',
                'expectedResult' => 'http://localhost',
                'expectedPort' => 80
            )),
            array(array(
                'REQUEST_URI' => '/',
                'SERVER_NAME' => 'localhost',
                'HTTP_HOST' => 'localhost',
                'expectedResult' => 'http://localhost/',
                'expectedPort' => 80
            )),array(array(
                'REQUEST_URI' => '/',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => 80,
                'expectedResult' => 'http://127.0.0.1/',
                'expectedPort' => 80
            )),
        );
    }
    
    /**
     * @dataProvider provideServerSuperglobalsForUriParsing
     * @covers Artax\Http\SuperglobalRequestDetector::detectUri
     * @covers Artax\Http\SuperglobalRequestDetector::attemptProxyStyleParse
     * @covers Artax\Http\SuperglobalRequestDetector::detectPath
     * @covers Artax\Http\SuperglobalRequestDetector::detectHost
     * @covers Artax\Http\SuperglobalRequestDetector::detectScheme
     * @covers Artax\Http\SuperglobalRequestDetector::detectPort
     * @covers Artax\Http\SuperglobalRequestDetector::detectQuery
     * @covers Artax\Http\SuperglobalRequestDetector::determineIpBasedHost
     * @covers Artax\Http\SuperglobalRequestDetector::isIpBasedHost
     */
    public function testMakeParsesUrlPropertiesFromSuperglobalArray($_server) {
        $detector = new SuperglobalRequestDetector;
        
        $uri = $detector->detectUri($_server);
        $this->assertEquals($_server['expectedResult'], $uri->__toString());
        $this->assertEquals($_server['expectedPort'], $uri->getPort());
    }
    
    /**
     * @covers Artax\Http\SuperglobalRequestDetector::detectUri
     * @covers Artax\Http\SuperglobalRequestDetector::detectHost
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnServerNameWithMissingHost() {
        $detector = new SuperglobalRequestDetector;
        $uri = $detector->detectUri(array('SERVER_NAME'=>'testhost'));
    }
    
    /**
     * @covers Artax\Http\SuperglobalRequestDetector::isIpBasedHost
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingServerName() {
        $detector = new SuperglobalRequestDetector;
        $uri = $detector->detectUri(array());
    }
    
    /**
     * @covers Artax\Http\SuperglobalRequestDetector::detectPath
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingPathKeys() {
        $detector = new SuperglobalRequestDetector;
        $uri = $detector->detectUri(array('SERVER_NAME' => 'test', 'HTTP_HOST' => 'test'));
    }
    
    /**
     * @covers Artax\Http\SuperglobalRequestDetector::detectPort
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingPortKeyOnIpBasedHost() {
        $detector = new SuperglobalRequestDetector;
        $uri = $detector->detectUri(array('SERVER_NAME' => '127.0.0.1'));
    }
    
}
