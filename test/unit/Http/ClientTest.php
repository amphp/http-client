<?php

use Artax\Http\Client,
    Artax\Http\StdRequest,
    Artax\Http\StdResponse;

class ClientTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::request
     * @expectedException RuntimeException
     */
    public function testRequestThrowsExceptionIfBuildStreamReturnsFalse() {
        $client = $this->getMock(
            'Artax\\Http\\Client',
            array('buildStreamContext', 'buildStream')
        );
        $client->expects($this->any())
               ->method('buildStream')
               ->will($this->returnValue(false));
               
        $client->request($this->getMock('Artax\\Http\\Request'));
    }
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::buildStream
     * @covers Artax\Http\Client::buildStreamContext
     * @covers Artax\Http\Client::getStreamMetaData
     * @covers Artax\Http\Client::getStreamBodyData
     * @covers Artax\Http\Client::buildResponse
     */
    public function testRequestReturnsPopulatedResponse() {
        $this->markTestIncomplete();
        /*
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'HttpStreamWrapperStub');
        
        $stub = new HttpStreamWrapperStub();
        $wrapperData = $stub->getWrapperData();
        $expectedHeaders = new StdResponse;
        foreach ($stub->getWrapperData() as $headerLine) {
            if (!(strpos($headerLine, 'HTTP/') === 0)) {
                $expectedHeaders->setRawHeader($headerLine);
            }
        }
        
        $client = new Client();
        
        // The request's headers/body are ignored and simulated by the stream wrapper stub
        $request = new StdRequest('http://test', 'PUT');
        
        $response = $client->request($request);
        
        $this->assertInstanceOf('Artax\\Http\\Response', $response);
        $this->assertEquals('1.1', $response->getHttpVersion());
        $this->assertEquals($expectedHeaders->getAllHeaders(), $response->getAllHeaders());
        $this->assertEquals('test body data', $response->getBody());
        */
    }
    
    /**
     * @covers Artax\Http\Client::setMaxRedirects
     */
    public function testSetMaxRedirectsAssignsContextValue() {
        $client = new Client;
        $client->setMaxRedirects(99);
    }
}

class HttpStreamWrapperStub implements IteratorAggregate, ArrayAccess {
    
    public $context;
    private $position = 0;
    private $bodyData = 'test body data';
    private $wrapperData = array(
        'HTTP/1.1 200 OK',
        'Content-Type: text/html; charset=UTF-8',
        'Connection: close',
        'Date: Fri, 16 Oct 2009 12:00:00 GMT',
        'Content-Length: 438'
    );
    
    public function getWrapperData() {
        return $this->wrapperData;
    }
    
    public function getIterator() {
        return new ArrayIterator($this->wrapperData);
    }
    public function offsetExists($offset) { return array_key_exists($offset, $this->wrapperData); }
    public function offsetGet($offset ) { return $this->wrapperData[$offset]; }
    public function offsetSet($offset, $value) { $this->wrapperData[$offset] = $value; }
    public function offsetUnset($offset) { unset($this->wrapperData[$offset]); }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }

    public function stream_read($count) {
        $this->position += strlen($this->bodyData);
        if ($this->position > strlen($this->bodyData)) {
            return false;
        }
        return $this->bodyData;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->bodyData);
    }
    
    public function stream_stat() {
        return array();
    }
    
    public function stream_tell() {
        return $this->position;
    }
    
    public function stream_metadata($path, $option, $var) {
        return true;
    }
}

class HttpStreamWrapperRedirectStub extends HttpStreamWrapperStub {
    protected $wrapperData = array(
    
    );
}















