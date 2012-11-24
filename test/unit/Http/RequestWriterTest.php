<?php

use Artax\Http\RequestWriter,
    Artax\Http\ValueRequest;

class RequestWriterTest extends PHPUnit_Framework_TestCase {
    
    public function provideWriteExpectations() {
        $return = array();
        
        // 0 -------------------------------------------------------------------------------------->
        
        $body = 'ZANZIBAR!';
        $headers = array(
            'Host' => 'www.google.com',
            'My-Test-Header' => 'woot!',
            'Content-Length' => strlen($body)
        );
        $request = new ValueRequest('GET', 'http://www.google.com/test', 1.1, $headers, $body);
        
        $expected = '' .
            "GET /test HTTP/1.1\r\n" .
            "Host: www.google.com\r\n" .
            "My-Test-Header: woot!\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "\r\n" .
            $body
        ;
        
        $return[] = array($request, $expected);
        
        // 1 -------------------------------------------------------------------------------------->
        
        $request = new ValueRequest('GET', 'http://localhost', 1.0, array(
            'Host' => 'localhost',
            'My-Test-Header' => 'woot!'
        ));
        
        $expected = '' .
            "GET / HTTP/1.0\r\n" .
            "Host: localhost\r\n" .
            "My-Test-Header: woot!\r\n" .
            "\r\n"
        ;
        
        $return[] = array($request, $expected);
        
        // 2 -------------------------------------------------------------------------------------->
        
        $request = new ValueRequest('TRACE', 'http://localhost', 1.1, array(
            'Host' => 'localhost',
            'My-Test-Header' => 'woot!'
        ), 'test body'); // <--- entity body should be removed for TRACE requests
        
        $expected = '' .
            "TRACE / HTTP/1.1\r\n" .
            "Host: localhost\r\n" .
            "My-Test-Header: woot!\r\n" .
            "\r\n"
        ;
        
        $return[] = array($request, $expected);
        
        // x -------------------------------------------------------------------------------------->
        
        return $return;
        
    }
    
    /**
     * @dataProvider provideWriteExpectations
     */
    public function testWrite($request, $expected) {
        $destination = fopen('php://memory', 'r+');
        $writer = new RequestWriter($request, $destination);
        $writer->send();
        
        rewind($destination);
        $actual = stream_get_contents($destination);
        
        $this->assertEquals($expected, $actual);
    }
    
}