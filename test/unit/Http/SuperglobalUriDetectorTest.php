<?php

use Artax\Http\SuperglobalUriDetector;

class SuperglobalUriDetectorTest extends PHPUnit_Framework_TestCase {
    
    public function provideServerSuperglobalsForParsing() {
        return array(
            array(array(
                'REQUEST_URI' => '/index.html',
                'HTTP_HOST' => 'localhost',
                'expectedResult' => 'http://localhost/index.html'
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => 'localhost',
                'HTTPS' => 'off',
                'SERVER_PORT' => 8080,
                'expectedResult' => 'http://localhost:8080/redirect.php'
            )),
            array(array(
                'REQUEST_URI' => '/index.php?queryVar=test',
                'QUERY_STRING' => 'queryVar=test',
                'HTTP_HOST' => 'localhost',
                'HTTPS' => true,
                'expectedResult' => 'https://localhost/index.php?queryVar=test'
            )),
            array(array(
                'REQUEST_URI' => 'http://localhost',
                'expectedResult' => 'http://localhost'
            )),
            array(array(
                'REQUEST_URI' => 'http://localhost/',
                'expectedResult' => 'http://localhost/'
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
     */
    public function testMakeParsesUrlPropertiesFromSuperglobalArray($_server) {
        $detector = new SuperglobalUriDetector;
        
        $uri = $detector->make($_server);
        $this->assertEquals($_server['expectedResult'], $uri->__toString());
    }
    
    /**
     * @covers Artax\Http\SuperglobalUriDetector::make
     * @covers Artax\Http\SuperglobalUriDetector::detectPath
     * @expectedException RuntimeException
     */
    public function testMakeThrowsExceptionOnMissingUriKeys() {
        $detector = new SuperglobalUriDetector;
        $uri = $detector->make(array('HTTPS'=>'On'));
    }
}
