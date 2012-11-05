<?php

use Artax\Http\SendableResponse;

class SendableResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\SendableResponse::send
     * @covers Artax\Http\SendableResponse::wasSent
     * @covers Artax\Http\SendableResponse::sendHeaders
     * @runInSeparateProcess
     */
    public function testSendOutputsHeadersAndBodyAndReturnsNull() {
        $response = new SendableResponse(200, 'OK');
        $response->setAllHeaders(array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        ));
        
        $this->assertNull($response->send());
        $this->assertTrue($response->wasSent());
    }
    
    /**
     * @covers Artax\Http\SendableResponse::send
     * @covers Artax\Http\SendableResponse::normalizeHeadersForSend
     */
    public function testSendSetsTransferEncodingChunkedHeaderOnStreamableResponseBody() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = $this->getMock(
            'Artax\\Http\\SendableResponse',
            array('sendHeaders', 'sendBody')
        );
        $response->setAllHeaders(array(
            'Content-Type' => 'text/html',
            'CONTENT-LENGTH' => 42
        ));
        $response->setBody($body);
        $response->send();
        
        $expectedHeaders = array(
            'Content-Type' => 'text/html',
            'Transfer-Encoding' => 'chunked'
        );
        
        $this->assertEquals($expectedHeaders, $response->getAllHeaders());
    }
    
    /**
     * @covers Artax\Http\SendableResponse::send
     * @covers Artax\Http\SendableResponse::normalizeHeadersForSend
     */
    public function testSendAddsContentLengthHeaderForNonStreamEntityBody() {
        $body = 'body text';
        $response = $this->getMock(
            'Artax\\Http\\SendableResponse',
            array('sendHeaders', 'sendBody')
        );
        $response->setBody($body);
        $response->send();
        $this->assertEquals(strlen($body), $response->getHeader('Content-Length'));
    }
    
    /**
     * @covers Artax\Http\SendableResponse::send
     * @covers Artax\Http\SendableResponse::sendBody
     */
    public function testSendOutputsStringBody() {
        $response = $this->getMock(
            'Artax\\Http\\SendableResponse',
            array('sendHeaders')
        );
        $body = 'test';
        $response->setBody($body);
        $this->expectOutputString($body);
        $response->send();
    }
    
    /**
     * @covers Artax\Http\SendableResponse::send
     * @covers Artax\Http\SendableResponse::sendBody
     */
    public function testSendStreamsEntityBodyIfPossible() {
        $contents = 'test';
        $body = fopen('php://memory', 'r+');
        fwrite($body, $contents);
        rewind($body);
        
        $expectedOutput = dechex(strlen($contents)) . "\r\n$contents\r\n0\r\n\r\n";
        
        $response = $this->getMock('Artax\\Http\\SendableResponse', array('sendHeaders'));
        $response->setBody($body);
        $this->expectOutputString($expectedOutput);
        $response->send();
        
        fclose($body);
    }
    
    /**
     * @covers Artax\Http\SendableResponse::normalizeHeadersForSend
     */
    public function testSendRemovesInvalidTransferEncodingAndContentLengthHeaders() {
        $response = $this->getMock('Artax\\Http\\SendableResponse', array('sendHeaders'));
        
        $response->setStartLine('HTTP/1.0 201 Created');
        $response->setHeader('Content-Length', 42);
        $response->setHeader('Transfer-Encoding', 'chunked');
        $response->send();
        
        $this->assertFalse($response->hasHeader('Content-Length'));
        $this->assertFalse($response->hasHeader('Transfer-Encoding'));
    }
}
