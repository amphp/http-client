<?php

use Artax\Streams\Socket;

class SocketTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Streams\Socket::__construct
     */
    public function testBeginsEmpty() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = new Socket($uri);
        $this->assertInstanceOf('Artax\\Streams\\Socket', $stream);
    }
    
    /**
     * @covers Artax\Streams\Socket::__construct
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionIfNoPortSpecifiedInUri() {
        $uri = new Artax\Uri('tcp://localhost');
        $stream = new Socket($uri);
    }
    
    /**
     * @covers Artax\Streams\Socket::setConnectTimeout
     */
    public function testSetConnectTimeout() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = new Socket($uri);
        $stream->setConnectTimeout(42);
    }
    
    /**
     * @covers Artax\Streams\Socket::setConnectFlags
     */
    public function testSetConnectFlags() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = new Socket($uri);
        $stream->setConnectFlags(STREAM_CLIENT_CONNECT);
    }
    
    /**
     * @covers Artax\Streams\Socket::setContextOptions
     */
    public function testSetContextOptions() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = new Socket($uri);
        $stream->setContextOptions(array());
    }
    
    /**
     * @covers Artax\Streams\Socket::open
     */
    public function testOpenAssignsSocketToResource() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
            array('doConnect'),
            array($uri)
        );
        
        $doConnectReturn = array(42, 0, null);
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue($doConnectReturn));
        
        $stream->open();
        $this->assertEquals(42, $stream->getResource());
    }
    
    /**
     * @covers Artax\Streams\Socket::open
     * @expectedException Artax\Streams\ConnectException
     */
    public function testOpenThrowsExceptionOnOpenSslError() {
        $uri = new Artax\Uri('tcp://localhost:80');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
            array('doConnect', 'getOpenSslError'),
            array($uri)
        );
        
        $doConnectReturn = array(false, 0, null);
        
        $stream->expects($this->once())
               ->method('doConnect')
               ->will($this->returnValue($doConnectReturn));
        $stream->expects($this->once())
               ->method('getOpenSslError')
               ->will($this->returnValue('test error'));
        
        $stream->open();
    }
    
    /**
     * @covers Artax\Streams\Socket::getScheme
     */
    public function testSchemeGetterReturnsComposedUriScheme() {
        $uri = new Artax\Uri('tls://localhost:80');
        $stream = new Socket($uri);
        $this->assertEquals('tls', $stream->getScheme());
    }
    
    /**
     * @covers Artax\Streams\Socket::getHost
     */
    public function testHostGetterReturnsComposedUriHost() {
        $uri = new Artax\Uri('tcp://localhost:8096');
        $stream = new Socket($uri);
        $this->assertEquals('localhost', $stream->getHost());
    }
    
    /**
     * @covers Artax\Streams\Socket::getPort
     */
    public function testPortGetterReturnsComposedUriPort() {
        $uri = new Artax\Uri('tcp://localhost:8096');
        $stream = new Socket($uri);
        $this->assertEquals(8096, $stream->getPort());
    }
    
    /**
     * @covers Artax\Streams\Socket::getPath
     */
    public function testPathGetterReturnsComposedUriPath() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new Socket($uri);
        $this->assertEquals('/path', $stream->getPath());
    }
    
    /**
     * @covers Artax\Streams\Socket::getUri
     */
    public function testUriGetterReturnsComposedUriString() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new Socket($uri);
        $this->assertEquals('tcp://localhost:8096/path', $stream->getUri());
    }
    
    /**
     * @covers Artax\Streams\Socket::getAuthority
     */
    public function testAuthorityGetterReturnsComposedAuthority() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = new Socket($uri);
        $this->assertEquals('localhost:8096', $stream->getAuthority());
    }
    
    /**
     * @covers Artax\Streams\Socket::isConnected
     */
    public function testIsConnectedReturnsBooleanOnConnectionStatus() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::getResource
     */
    public function testGetResourceReturnsConnectedSocketResource() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::close
     */
    public function testCloseSetsStreamPropertyToNull() {
        $uri = new Artax\Uri('tcp://localhost:8096/path');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::open
     * @covers Artax\Streams\Socket::doConnect
     * @expectedException Artax\Streams\ConnectException
     */
    public function testConnectThrowsExceptionOnFailedSocketConnectionAttempt() {
        $uri = new Artax\Uri('tcp://some-url-that-should-definitely-not-exist:8042');
        $stream = new Socket($uri);
        $stream->open();
    }
    
    /**
     * @covers Artax\Streams\Socket::read
     * @covers Artax\Streams\Socket::doRead
     * @expectedException Artax\Streams\IoException
     */
    public function testReadThrowsExceptionOnFailure() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::read
     * @covers Artax\Streams\Socket::doRead
     */
    public function testReadReturnsStringReadBufferOnSuccess() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::read
     * @covers Artax\Streams\Socket::doRead
     * @covers Artax\Streams\Socket::getBytesRecd
     */
    public function testReadUpdatesBytesRecdTotal() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::write
     * @covers Artax\Streams\Socket::doWrite
     */
    public function testWriteReturnsNumberOfBytesWrittenOnSuccess() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::write
     * @expectedException Artax\Streams\IoException
     */
    public function testWriteThrowsExceptionOnFailure() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::write
     * @covers Artax\Streams\Socket::getBytesSent
     * @covers Artax\Streams\Socket::getActivityTimestamp
     */
    public function testWriteUpdatesActivityTimestampOnSuccess() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
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
     * @covers Artax\Streams\Socket::__destruct
     */
    public function testDestructorClosesConnection() {
        $uri = new Artax\Uri('tcp://localhost:8042');
        $stream = $this->getMock(
            'Artax\\Streams\\Socket',
            array('close'),
            array($uri)
        );
        
        $stream->expects($this->once())
               ->method('close');
        
        $stream->__destruct();
    }
}

























