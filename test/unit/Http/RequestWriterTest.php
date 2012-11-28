<?php

use Artax\Http\RequestWriter,
    Artax\Http\ValueRequest,
    Spl\HashingMediator;

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
    
    /**
     * @expectedException Spl\TypeException
     */
    public function testConstructThrowsExceptionOnInvalidDestination() {
        $request = new ValueRequest('GET', '/test', 1.1);
        $writer = new RequestWriter($request, 'happy hour is not a valid stream');
    }
    
    public function testExpectsContinue() {
        $headers = array(
            'Expect' => '100-continue',
            'Content-Length' => 5
        );
        $body = 'woot!';
        $request = new ValueRequest('GET', '/test', 1.1, $headers, $body);
    
        $dest = fopen('php://memory', 'r+');
        $writer = new RequestWriter($request, $dest);
        
        $this->assertFalse($writer->expectsContinue());
        
        $expectContinue = 0;
        while (!$writer->send()) {
            if ($writer->expectsContinue() && !$expectContinue) {
                ++$expectContinue;
            } elseif ($writer->expectsContinue()) {
                $writer->allowContinue();
            }
        }
        
        rewind($dest);
        $this->assertEquals($request->__toString(), stream_get_contents($dest));
    }
    
    public function testWriteProceedsAutomaticallyIfNoContinueReceived() {
        $headers = array(
            'Expect' => '100-continue',
            'Content-Length' => 5
        );
        $body = 'woot!';
        $request = new ValueRequest('GET', '/test', 1.1, $headers, $body);
        
        $dest = fopen('php://memory', 'r+');
        
        $writer = new RequestWriter($request, $dest);
        $writer->setAttribute(RequestWriter::ATTR_100_CONTINUE_DELAY, 0.1);
        
        while (!$writer->send()) {
            sleep(0.11);
        }
    }
    
    /**
     * @expectedException Spl\DomainException
     */
    public function testSetAttributeThrowsExceptionIfWriteAlreadyStarted() {
        $headers = array(
            'Expect' => '100-continue',
            'Content-Length' => 5
        );
        $body = 'woot!';
        $request = new ValueRequest('GET', '/test', 1.1, $headers, $body);
        
        $dest = fopen('php://memory', 'r+');
        $writer = new RequestWriter($request, $dest);
        
        while (!$writer->send()) {
            $writer->setAttribute(RequestWriter::ATTR_100_CONTINUE_DELAY, 42);
        }
    }
    
    /**
     * @expectedException Spl\KeyException
     */
    public function testSetAttributeThrowsExceptionOnInvalidAttribute() {
        $request = new ValueRequest('GET', '/test', 1.1);
        $dest = fopen('php://memory', 'r+');
        $writer = new RequestWriter($request, $dest);
        
        $writer->setAttribute('happy hour is not a valid attribute', 42);
    }
    
    public function testSendWritesChunkEncodedStreamBody() {
        $bodyContent = 'testbody';
        $body = fopen('data://text/plain;base64,' . base64_encode($bodyContent), 'r');
        
        $request = new ValueRequest('POST', 'http://localhost', 1.1, array(
            'Host' => 'localhost',
            'My-Test-Header' => 'woot!',
            'Transfer-Encoding' => 'chunked'
        ), $body);
        
        $expected = '' .
            "POST / HTTP/1.1\r\n" .
            "Host: localhost\r\n" .
            "My-Test-Header: woot!\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "4\r\n" .
            "test" .
            "\r\n" .
            "4\r\n" .
            "body" .
            "\r\n" .
            "0\r\n"
        ;
        
        $destination = fopen('php://memory', 'r+');
        
        $writer = new RequestWriter($request, $destination);
        $writer->setAttribute(RequestWriter::ATTR_STREAM_BUFFER_SIZE, 4);
        $writer->send();
        
        rewind($destination);
        
        $this->assertEquals($expected, stream_get_contents($destination));
    }
    
    public function testSendStreamsSeekableResourceBodyWithoutTransferEncoding() {
        $bodyContent = 'test body';
        $body = fopen('data://text/plain;base64,' . base64_encode($bodyContent), 'r');
        
        $request = new ValueRequest('POST', 'http://localhost', 1.1, array(
            'Host' => 'localhost',
            'My-Test-Header' => 'woot!',
            'Content-Length' => strlen($bodyContent)
        ), $body);
        
        $expected = '' .
            "POST / HTTP/1.1\r\n" .
            "Host: localhost\r\n" .
            "My-Test-Header: woot!\r\n" .
            "Content-Length: " . strlen($bodyContent) . "\r\n" .
            "\r\n" .
            $bodyContent
        ;
        
        $destination = fopen('php://memory', 'r+');
        
        $writer = new RequestWriter($request, $destination);
        $writer->setAttribute(RequestWriter::ATTR_STREAM_BUFFER_SIZE, 1);
        $writer->send();
        
        rewind($destination);
        
        $this->assertEquals($expected, stream_get_contents($destination));
    }
    
    public function testSendStreamsSeekableResourceBodyWithoutChunkEncoding() {
        $bodyContent = 'test body';
        $body = fopen('data://text/plain;base64,' . base64_encode($bodyContent), 'r');
        
        $request = new ValueRequest('POST', 'http://localhost', 1.1, array(
            'Host' => 'localhost',
            'My-Test-Header' => 'woot!',
            'Transfer-Encoding' => 'something that isnt chunked',
            'Content-Length' => strlen($bodyContent)
        ), $body);
        
        $expected = '' .
            "POST / HTTP/1.1\r\n" .
            "Host: localhost\r\n" .
            "My-Test-Header: woot!\r\n" .
            "Transfer-Encoding: something that isnt chunked\r\n" .
            "Content-Length: " . strlen($bodyContent) . "\r\n" .
            "\r\n" .
            $bodyContent
        ;
        
        $destination = fopen('php://memory', 'r+');
        
        $writer = new RequestWriter($request, $destination);
        $writer->setAttribute(RequestWriter::ATTR_STREAM_BUFFER_SIZE, 1);
        $writer->send();
        
        rewind($destination);
        
        $this->assertEquals($expected, stream_get_contents($destination));
    }
    
    public function testSendStreamsSeekableResourceBodyOnInsufficientHttpProtocol() {
        $bodyContent = 'test body';
        $body = fopen('data://text/plain;base64,' . base64_encode($bodyContent), 'r');
        
        $request = new ValueRequest('POST', 'http://localhost', 1.0, array(
            'Host' => 'localhost',
            'My-Test-Header' => 'woot!',
            'Transfer-Encoding' => 'chunked',
            'Content-Length' => strlen($bodyContent)
        ), $body);
        
        $expected = '' .
            "POST / HTTP/1.0\r\n" .
            "Host: localhost\r\n" .
            "My-Test-Header: woot!\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "Content-Length: " . strlen($bodyContent) . "\r\n" .
            "\r\n" .
            $bodyContent
        ;
        
        $destination = fopen('php://memory', 'r+');
        
        $writer = new RequestWriter($request, $destination);
        $writer->setAttribute(RequestWriter::ATTR_STREAM_BUFFER_SIZE, 1);
        $writer->send();
        
        rewind($destination);
        
        $this->assertEquals($expected, stream_get_contents($destination));
    }
    
    public function testSendNotifiesListenersIfMediatorAssigned() {
        $notified = '';
        $mediator = new HashingMediator();
        $mediator->addListener(RequestWriter::EVENT_WRITE, function($writer, $data, $bytes) use (&$notified) {
            $notified .= $data;
        });
        
        $request = new ValueRequest('GET', 'http://localhost', 1.0, array('Host' => 'localhost'));
        
        $destination = fopen('php://memory', 'r+');
        
        $writer = new RequestWriter($request, $destination, $mediator);
        $writer->send();
        
        rewind($destination);
        $destContents = stream_get_contents($destination);
        
        $this->assertEquals($request->__toString(), $destContents);
        $this->assertEquals($destContents, $notified);
    }
    
    
    
    
}

















