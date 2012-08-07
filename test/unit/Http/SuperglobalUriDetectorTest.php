<?php

use Artax\Http\SuperglobalUriDetector;

class SuperglobalUriDetectorTest extends PHPUnit_Framework_TestCase {
    
    public function provideServerSuperglobalsForParsing() {
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
     * @dataProvider provideServerSuperglobalsForParsing
     * @covers Artax\Http\SuperglobalUriDetector::make
     * @covers Artax\Http\SuperglobalUriDetector::attemptProxyStyleParse
     * @covers Artax\Http\SuperglobalUriDetector::detectPath
     * @covers Artax\Http\SuperglobalUriDetector::detectHost
     * @covers Artax\Http\SuperglobalUriDetector::detectScheme
     * @covers Artax\Http\SuperglobalUriDetector::detectPort
     * @covers Artax\Http\SuperglobalUriDetector::detectQuery
     * @covers Artax\Http\SuperglobalUriDetector::determineIpBasedHost
     * @covers Artax\Http\SuperglobalUriDetector::isIpBasedHost
     */
    public function testMakeParsesUrlPropertiesFromSuperglobalArray($_server) {
        $detector = new SuperglobalUriDetector;
        
        $uri = $detector->make($_server);
        $this->assertEquals($_server['expectedResult'], $uri->__toString());
        $this->assertEquals($_server['expectedPort'], $uri->getPort());
    }
    
    /**
     * @covers Artax\Http\SuperglobalUriDetector::make
     * @covers Artax\Http\SuperglobalUriDetector::detectHost
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnServerNameWithMissingHost() {
        $detector = new SuperglobalUriDetector;
        $uri = $detector->make(array('SERVER_NAME'=>'testhost'));
    }
    
    /**
     * @covers Artax\Http\SuperglobalUriDetector::isIpBasedHost
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingServerName() {
        $detector = new SuperglobalUriDetector;
        $uri = $detector->make(array());
    }
    
    /**
     * @covers Artax\Http\SuperglobalUriDetector::detectPath
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingPathKeys() {
        $detector = new SuperglobalUriDetector;
        $uri = $detector->make(array('SERVER_NAME' => 'test', 'HTTP_HOST' => 'test'));
    }
    
    /**
     * @covers Artax\Http\SuperglobalUriDetector::detectPort
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingPortKeyOnIpBasedHost() {
        $detector = new SuperglobalUriDetector;
        $uri = $detector->make(array('SERVER_NAME' => '127.0.0.1'));
    }
}
