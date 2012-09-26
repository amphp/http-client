<?php

use Artax\Streams\SocketStream;

class SocketStreamTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Streams\SocketStream::__construct
     */
    public function testBeginsEmpty() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = new SocketStream($mediator, $uri);
        $this->assertInstanceOf('Artax\\Streams\\SocketStream', $stream);
    }
    
    /**
     * @covers Artax\Streams\SocketStream::__construct
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionIfNoPortSpecifiedInUri() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost');
        $stream = new SocketStream($mediator, $uri);
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getScheme
     */
    public function testSchemeGetterReturnsComposedUriScheme() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tls://localhost:80');
        $stream = new SocketStream($mediator, $uri);
        $this->assertEquals('tls', $stream->getScheme());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getHost
     */
    public function testHostGetterReturnsComposedUriHost() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096');
        $stream = new SocketStream($mediator, $uri);
        $this->assertEquals('localhost', $stream->getHost());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getPort
     */
    public function testPortGetterReturnsComposedUriPort() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096');
        $stream = new SocketStream($mediator, $uri);
        $this->assertEquals(8096, $stream->getPort());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getPath
     */
    public function testPathGetterReturnsComposedUriPath() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new SocketStream($mediator, $uri);
        $this->assertEquals('/path', $stream->getPath());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getUri
     */
    public function testUriGetterReturnsComposedUriString() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new SocketStream($mediator, $uri);
        $this->assertEquals('tcp://localhost:8096/path', $stream->getUri());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getAuthority
     */
    public function testAuthorityGetterReturnsComposedAuthority() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new SocketStream($mediator, $uri);
        $this->assertEquals('localhost:8096', $stream->getAuthority());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::isConnected
     */
    public function testIsConnectedReturnsBooleanOnConnectionStatus() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($mediator, $uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->connect();
        $this->assertTrue($stream->isConnected());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::getResource
     */
    public function testGetResourceReturnsConnectedSocketStreamResource() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($mediator, $uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->connect();
        $this->assertEquals($memoryStream, $stream->getResource());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::close
     */
    public function testCloseSetsStreamPropertyToNull() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($mediator, $uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->connect();
        $this->assertEquals($memoryStream, $stream->getResource());
        $stream->close();
        $this->assertNull($stream->getResource());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::close
     */
    public function testCloseNotifiesListeners() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($mediator, $uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->connect();
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with(SocketStream::EVENT_CLOSE, $stream);
        
        $stream->close();
    }
    
    /**
     * @covers Artax\Streams\SocketStream::connect
     * @covers Artax\Streams\SocketStream::doConnect
     * @expectedException Artax\Streams\ConnectException
     */
    public function testConnectThrowsExceptionOnFailedSocketConnectionAttempt() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://some-url-that-should-definitely-not-exist:8042');
        $stream = new SocketStream($mediator, $uri);
        $stream->connect();
    }
    
    /**
     * @covers Artax\Streams\SocketStream::connect
     */
    public function testConnectNotifiesListener() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect'),
            array($mediator, $uri)
        );
        
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with(SocketStream::EVENT_OPEN, $stream);
        
        $stream->connect();
    }
    
    /**
     * @covers Artax\Streams\SocketStream::read
     * @covers Artax\Streams\SocketStream::doRead
     * @expectedException Artax\Streams\IoException
     */
    public function testReadThrowsExceptionOnFailure() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doRead'),
            array($mediator, $uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $stream->expects($this->once())
               ->method('doRead')
               ->will($this->returnValue(false));
        
        $stream->connect();
        $stream->read(4096);
    }
    
    /**
     * @covers Artax\Streams\SocketStream::read
     * @covers Artax\Streams\SocketStream::getBytesRecd
     * @covers Artax\Streams\SocketStream::getActivityTimestamp
     */
    public function testReadNotifiesListenersAndUpdatesActivityTimestampOnSuccess() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doRead', '__destruct'),
            array($mediator, $uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $readData = 'Eddard Stark';
        $readDataLen = strlen($readData);
        $stream->expects($this->once())
               ->method('doRead')
               ->will($this->returnValue($readData));
        
        $stream->connect();
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with(SocketStream::EVENT_READ, $stream, $readData, $readDataLen);
        
        $oldActivityTimestamp = $stream->getActivityTimestamp();
        $this->assertEquals($readData, $stream->read(4096));
        $this->assertTrue($stream->getActivityTimestamp() > $oldActivityTimestamp);
        $this->assertEquals($readDataLen, $stream->getBytesRecd());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::read
     * @covers Artax\Streams\SocketStream::doRead
     */
    public function testReadReturnsStringReadBufferOnSuccess() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', '__destruct'),
            array($mediator, $uri)
        );
        
        $readData = 'Eddard Stark';
        $memoryStream = fopen('php://temp', 'r+');
        fwrite($memoryStream, $readData);
        rewind($memoryStream);
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->connect();
        $this->assertEquals($readData, $stream->read(4096));
    }
    
    /**
     * @covers Artax\Streams\SocketStream::write
     * @covers Artax\Streams\SocketStream::doWrite
     */
    public function testWriteReturnsNumberOfBytesWrittenOnSuccess() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', '__destruct'),
            array($mediator, $uri)
        );
        
        $writeData = 'Eddard Stark';
        $writeDataLen = strlen($writeData);
        $memoryStream = fopen('php://temp', 'r+');
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array($memoryStream, 0, '')));
        
        $stream->connect();
        $this->assertEquals($writeDataLen, $stream->write($writeData));
        rewind($memoryStream);
        $this->assertEquals($writeData, stream_get_contents($memoryStream));
    }
    
    /**
     * @covers Artax\Streams\SocketStream::write
     * @expectedException Artax\Streams\IoException
     */
    public function testWriteThrowsExceptionOnFailure() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doWrite', '__destruct'),
            array($mediator, $uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $stream->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(false));
        
        $stream->connect();
        $stream->write('test');
    }
    
    /**
     * @covers Artax\Streams\SocketStream::write
     * @covers Artax\Streams\SocketStream::getBytesSent
     * @covers Artax\Streams\SocketStream::getActivityTimestamp
     */
    public function testWriteNotifiesListenersAndUpdatesActivityTimestampOnSuccess() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('doConnect', 'doWrite', '__destruct'),
            array($mediator, $uri)
        );
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue(array(42, 0, '')));
        
        $writeData = 'Eddard Stark';
        $stream->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(6));
        
        $stream->connect();
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with(SocketStream::EVENT_WRITE, $stream, 'Eddard', 6);
        
        $oldActivityTimestamp = $stream->getActivityTimestamp();
        $this->assertEquals(6, $stream->write($writeData));
        $this->assertTrue($stream->getActivityTimestamp() > $oldActivityTimestamp);
        $this->assertEquals(6, $stream->getBytesSent());
    }
    
    /**
     * @covers Artax\Streams\SocketStream::__destruct
     */
    public function testDestructorClosesConnection() {
        $mediator = $this->getMock('Spl\\Mediator');
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\SocketStream',
            array('close'),
            array($mediator, $uri)
        );
        
        $stream->expects($this->once())
               ->method('close');
        
        $stream->__destruct();
    }
}

























