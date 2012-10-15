<?php

use Artax\Streams\Stream;

class StreamTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Streams\Stream::__construct
     */
    public function testBeginsEmpty() {
        $stream = new Stream('php://memory', 'r+');
        $this->assertInstanceOf('Artax\\Streams\\Stream', $stream);
    }
    
    /**
     * @covers Artax\Streams\Stream::open
     * @expectedException Artax\Streams\StreamException
     */
    public function testOpenThrowsExceptionOnFopenFailure() {
        $stream = $this->getMock(
            'Artax\\Streams\\Stream',
            array('doOpen'),
            array('php://memory', 'r+')
        );
        $stream->expects($this->once())
               ->method('doOpen')
               ->will($this->returnValue(false));
        $stream->open();
    }
    
    /**
     * @covers Artax\Streams\Stream::open
     * @covers Artax\Streams\Stream::doOpen
     */
    public function testOpenStoresResource() {
        $stream = new Stream('php://memory', 'r+');
        $stream->open();
        $this->assertTrue(is_resource($stream->getResource()));
    }
    
    /**
     * @covers Artax\Streams\Stream::read
     * @covers Artax\Streams\Stream::getResourceId
     * @expectedException Artax\Streams\StreamException
     */
    public function testReadThrowsExceptionOnFreadFailure() {
        $stream = $this->getMock(
            'Artax\\Streams\\Stream',
            array('doRead'),
            array('php://memory', 'r+')
        );
        $stream->expects($this->once())
               ->method('doRead')
               ->will($this->returnValue(false));
        $stream->open();
        $stream->read(42);
    }
    
    /**
     * @covers Artax\Streams\Stream::read
     */
    public function testReadReturnsDataFromFread() {
        $stream = new Stream('php://memory', 'r+');
        $stream->open();
        $stream->write('test');
        rewind($stream->getResource());
        $this->assertEquals('tes', $stream->read(3));
    }
    
    /**
     * @covers Artax\Streams\Stream::write
     * @covers Artax\Streams\Stream::getResourceId
     * @expectedException Artax\Streams\StreamException
     */
    public function testWriteThrowsExceptionOnFwriteFailure() {
        $stream = $this->getMock(
            'Artax\\Streams\\Stream',
            array('doWrite'),
            array('php://memory', 'r+')
        );
        $stream->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(false));
        $stream->open();
        $stream->write(42);
    }
    
    /**
     * @covers Artax\Streams\Stream::write
     */
    public function testWriteReturnsBytesWrittenByFread() {
        $stream = new Stream('php://memory', 'r+');
        $stream->open();
        $this->assertEquals(4, $stream->write('test'));
    }
}