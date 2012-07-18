<?php

use Artax\SuperglobalToUrlTranslator;

class SuperglobalToUrlTranslatorTest extends PHPUnit_Framework_TestCase {
    
    public function provideServerSuperglobalsForParsing() {
        return array(
            array(array(
                'REQUEST_URI' => '/index.html',
                'HTTP_HOST' => 'localhost'
            )),
            array(array(
                'REDIRECT_URL' => '/redirect.php',
                'HTTP_HOST' => 'localhost',
                'HTTPS' => 'off',
                'REMOTE_PORT' => 8080
            )),
            array(array(
                'REQUEST_URI' => '/index.php?queryVar=test',
                'QUERY_STRING' => 'queryVar=test',
                'HTTP_HOST' => 'localhost',
                'HTTPS' => true
            )),
        );
    }
    
    /**
     * @dataProvider provideServerSuperglobalsForParsing
     * @covers Artax\SuperglobalToUrlTranslator::make
     * @covers Artax\SuperglobalToUrlTranslator::detectPath
     * @covers Artax\SuperglobalToUrlTranslator::detectHost
     * @covers Artax\SuperglobalToUrlTranslator::detectScheme
     * @covers Artax\SuperglobalToUrlTranslator::detectPort
     * @covers Artax\SuperglobalToUrlTranslator::detectQuery
     */
    public function testMakeParsesUrlPropertiesFromSuperglobalArray($_server) {
        $translator = new SuperglobalToUrlTranslator;
        
        $expectedHost = $_server['HTTP_HOST'];
        $expectedPort = isset($_server['REMOTE_PORT']) ? $_server['REMOTE_PORT'] : 80;
        $expectedQuery = isset($_server['QUERY_STRING']) ? $_server['QUERY_STRING'] : '';
        
        $pathVar = isset($_server['REQUEST_URI']) ? $_server['REQUEST_URI'] : $_server['REDIRECT_URL'];
        $qPos = strpos($pathVar, '?');
        $expectedPath = $qPos !== FALSE ? substr($pathVar, 0, $qPos) : $pathVar;
        
        $expectedScheme = isset($_server['HTTPS']) && filter_var($_server['HTTPS'], FILTER_VALIDATE_BOOLEAN)
            ? 'https'
            : 'http';
        
        
        $url = $translator->make($_server);
        
        $this->assertEquals($expectedHost, $url->getHost());
        $this->assertEquals($expectedPath, $url->getPath());
        $this->assertEquals($expectedQuery, $url->getQuery());
        $this->assertEquals($expectedPort, $url->getPort());
        $this->assertEquals($expectedScheme, $url->getScheme());
        $this->assertEquals('', $url->getFragment());
    }
}
