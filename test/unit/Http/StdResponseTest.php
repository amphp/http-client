<?php

use Artax\Http\StdResponse;

class StdResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdResponse::__construct
     */
    public function testConstructorAssignsProperties() {
        $headers = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        
        $response = new StdResponse(200, 'OK', $headers, 'test');
        $this->assertEquals('test', $response->getBody());
        $this->assertEquals($headers, $response->getAllHeaders());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getStatusDescription());
    }
    
    /**
     * @covers Artax\Http\StdResponse::__toString
     */
    public function testToStringBuildsRawHttpResponseMessage() {
        $headers = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        
        $response = new StdResponse(200, 'OK', $headers, 'test');
        
        $expected = '' .
            "HTTP/1.1 200 OK\r\n" .
            "CONTENT-TYPE: text/html\r\n" .
            "CONTENT-LENGTH: 42\r\n" .
            "\r\n" .
            "test"
        ;
        
        $this->assertEquals($expected, $response->__toString());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getStatusCode
     */
    public function testStatusCodeAccessorMethodReturnsStatusCode() {
        $response = new StdResponse(404, 'Not Found');
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getStatusDescription
     */
    public function testStatusDescriptionAccessorReturnsDescription() {
        $response = new StdResponse(404, 'Not Found');
        $this->assertEquals('Not Found', $response->getStatusDescription());
    }
    
    /**
     * @covers Artax\Http\StdResponse::send
     * @covers Artax\Http\StdResponse::wasSent
     * @covers Artax\Http\StdResponse::sendHeaders
     * @runInSeparateProcess
     */
    public function testSendOutputsHeadersAndBodyAnReturnsNull() {
        $response = new StdResponse(200, 'OK', array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        ));
        
        $this->assertNull($response->send());
        $this->assertTrue($response->wasSent());
    }
    
    /**
     * @covers Artax\Http\StdResponse::send
     * @covers Artax\Http\StdResponse::removeContentLengthForChunkedBody
     */
    public function testSendRemovesContentLengthHeaderOnStreamableResponseBody() {
        $headers = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = $this->getMock(
            'Artax\\Http\\StdResponse',
            array('sendBody', 'sendHeaders'),
            array(200, 'OK', $headers, $body)
        );
        
        $response->send();
        
        unset($headers['CONTENT-LENGTH']);
        
        $this->assertEquals($headers, $response->getAllHeaders());
    }
    
    /**
     * @covers Artax\Http\StdResponse::send
     * @covers Artax\Http\StdResponse::sendBody
     */
    public function testSendOutputsStringBody() {
        $body = 'test';
        $response = $this->getMock(
            'Artax\\Http\\StdResponse',
            array('sendHeaders'),
            array(200, 'OK', null, $body)
        );
        
        $this->expectOutputString($body);
        $response->send();
    }
    
    /**
     * @covers Artax\Http\StdResponse::send
     * @covers Artax\Http\StdResponse::sendBody
     * @covers Artax\Http\StdResponse::outputBodyChunk
     */
    public function testSendStreamsEntityBodyIfPossible() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = $this->getMock(
            'Artax\\Http\\StdResponse',
            array('sendHeaders'),
            array(200, 'OK', null, $body)
        );
        
        $this->expectOutputString(stream_get_contents($body));
        $response->send();
        
        fclose($body);
    }
    
    /**
     * @covers Artax\Http\StdResponse::send
     * @covers Artax\Http\StdResponse::sendBody
     * @expectedException RuntimeException
     */
    public function testSendThrowsExceptionOnStreamBodyOutputFailure() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = $this->getMock(
            'Artax\\Http\\StdResponse',
            array('sendHeaders', 'outputBodyChunk'),
            array(200, 'OK', null, $body)
        );
        
        $response->expects($this->any())
                 ->method('outputBodyChunk')
                 ->will($this->returnValue(false));
        
        $response->send();
    }
}
