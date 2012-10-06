<?php

use Artax\Streams\SocketStream;

class SocketStreamTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Streams\SocketStream::__construct
     */
    public function testBeginsEmpty() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = new SocketStream($uri);
        $this->assertInstanceOf('Artax\\Streams\\SocketStream', $stream);
    }
    
    /**
     * @covers Artax\Streams\SocketStream::__construct
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionIfNoPortSpecifiedInUri() {
        $uri = new Artax\Uri('tcp://localhost');
        $stream = new SocketStream($uri);
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getScheme
     */
    public function testSchemeGetterReturnsComposedUriScheme() {
        $uri = new Artax\Uri('tls://localhost:80');
        $stream = new SocketStream($uri);
        $this->assertEquals('tls', $stream->getScheme());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getHost
     */
    public function testHostGetterReturnsComposedUriHost() {
        $uri = new Artax\Uri('tcp://localhost:8096');
        $stream = new SocketStream($uri);
        $this->assertEquals('localhost', $stream->getHost());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getPort
     */
    public function testPortGetterReturnsComposedUriPort() {
        $uri = new Artax\Uri('tcp://localhost:8096');
        $stream = new SocketStream($uri);
        $this->assertEquals(8096, $stream->getPort());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getPath
     */
    public function testPathGetterReturnsComposedUriPath() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new SocketStream($uri);
        $this->assertEquals('/path', $stream->getPath());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getUri
     */
    public function testUriGetterReturnsComposedUriString() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new SocketStream($uri);
        $this->assertEquals('tcp://localhost:8096/path', $stream->getUri());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getAuthority
     */
    public function testAuthorityGetterReturnsComposedAuthority() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new SocketStream($uri);
        $this->assertEquals('localhost:8096', $stream->getAuthority());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::isConnected
     */
    public function testIsConnectedReturnsBooleanOnConnectionStatus() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $this->assertFalse($stream->isConnected());
        $stream->open();
        $this->assertTrue($stream->isConnected());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getResource
     */
    public function testGetResourceReturnsConnectedSocketStreamResource() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->open();
        $this->assertEquals($memoryStream, $stream->getResource());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::close
     */
    public function testCloseSetsStreamPropertyToNull() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->open();
        $this->assertEquals($memoryStream, $stream->getResource());
        $stream->close();
        $this->assertNull($stream->getResource());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::open
     * @covers Artax\Streams\SocketStream::doConnect
     * @expectedException Artax\Streams\ConnectException
     */
    public function testConnectThrowsExceptionOnFailedSocketConnectionAttempt() {
        $uri = new Artax\Uri('tcp://some-url-that-should-definitely-not-exist:8042');
        $stream = new SocketStream($uri);
        $stream->open();
    }
    
    /**
     * @covers Artax\Streams\SocketStream::read
     * @covers Artax\Streams\SocketStream::doRead
     * @expectedException Artax\Streams\IoException
     */
    public function testReadThrowsExceptionOnFailure() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doRead'),
            array($uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $stream->expects($this->once())
               ->method('doRead')
               ->will($this->returnValue(false));
        
        $stream->open();
        $stream->read(4096);
    }
    
    /**
     * @covers Artax\Streams\SocketStream::read
     * @covers Artax\Streams\SocketStream::doRead
     */
    public function testReadReturnsStringReadBufferOnSuccess() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', '__destruct'),
            array($uri)
        );
        
        $readData = 'Eddard Stark';
        $memoryStream = fopen('php://temp', 'r+');
        fwrite($memoryStream, $readData);
        rewind($memoryStream);
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->open();
        $this->assertEquals($readData, $stream->read(4096));
    }
    
    /**
     * @covers Artax\Streams\SocketStream::read
     * @covers Artax\Streams\SocketStream::doRead
     * @covers Artax\Streams\SocketStream::getBytesRecd
     */
    public function testReadUpdatesBytesRecdTotal() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', '__destruct'),
            array($uri)
        );
        
        $readData = 'Eddard Stark';
        $memoryStream = fopen('php://temp', 'r+');
        fwrite($memoryStream, $readData);
        rewind($memoryStream);
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->open();
        $stream->read(4096);
        $this->assertEquals(strlen($readData), $stream->getBytesRecd());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::write
     * @covers Artax\Streams\SocketStream::doWrite
     */
    public function testWriteReturnsNumberOfBytesWrittenOnSuccess() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', '__destruct'),
            array($uri)
        );
        
        $writeData = 'Eddard Stark';
        $writeDataLen = strlen($writeData);
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->open();
        $this->assertEquals($writeDataLen, $stream->write($writeData));
        rewind($memoryStream);
        $this->assertEquals($writeData, stream_get_contents($memoryStream));
    }
    
    /**
     * @covers Artax\Streams\SocketStream::write
     * @expectedException Artax\Streams\IoException
     */
    public function testWriteThrowsExceptionOnFailure() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doWrite', '__destruct'),
            array($uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $stream->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(false));
        
        $stream->open();
        $stream->write('test');
    }
    
    /**
     * @covers Artax\Streams\SocketStream::write
     * @covers Artax\Streams\SocketStream::getBytesSent
     * @covers Artax\Streams\SocketStream::getActivityTimestamp
     */
    public function testWriteUpdatesActivityTimestampOnSuccess() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doWrite', '__destruct'),
            array($uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $writeData = 'Eddard Stark';
        $stream->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(6));
        
        $stream->open();
        
        $oldActivityTimestamp = $stream->getActivityTimestamp();
        $this->assertEquals(6, $stream->write($writeData));
        $this->assertTrue($stream->getActivityTimestamp() > $oldActivityTimestamp);
        $this->assertEquals(6, $stream->getBytesSent());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::__destruct
     */
    public function testDestructorClosesConnection() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('close'),
            array($uri)
        );
        
        $stream->expects($this->once())
               ->method('close');
        
        $stream->__destruct();
    }
}

























