<?php

use Spl\HashingMediator,
    Artax\Uri,
    Artax\Client,
    Artax\Http\StdRequest,
    Artax\ChainableResponse,
    Artax\Streams\Stream,
    Artax\Streams\SocketResource;

/**
 * @covers Artax\ClientState
 * @covers Artax\Client
 */
class ClientTest extends PHPUnit_Framework_TestCase {
    
    protected function setUp() {
        stream_wrapper_unregister('http');
        stream_wrapper_unregister('https');
        stream_wrapper_register('http', 'ClientSocketStreamWrapper');
        stream_wrapper_register('https', 'ClientSocketStreamWrapper');
        ClientSocketStreamWrapper::reset();
    }
    
    public function tearDown() {
        ClientSocketStreamWrapper::reset();
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');
    }
    
    /**
     * @expectedException Spl\ValueException
     */
    public function testSetAttributeThrowsExceptionOnInvalidAttribute() {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $client->setAttribute('some attribute that doesnt exist', true);
    }
    
    /**
     * @expectedException Spl\ValueException
     */
    public function testGetAttributeThrowsExceptionOnInvalidAttribute() {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $client->getAttribute('some attribute that doesnt exist');
    }
    
    /**
     * @covers Artax\Client::setAllAttributes
     */
    public function testSetAllAttributesDelegatesToSetAttribute() {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = $this->getMock('Artax\\Client', array('setAttribute'), array($mediator));
        $client->expects($this->exactly(3))
               ->method('setAttribute');
        
        $client->setAllAttributes(array(
            'attr1' => 1,
            'attr2' => 2,
            'attr3' => 3
        ));
    }
    
    public function provideAttributeValues() {
        return array(
            array(Client::ATTR_KEEP_CONNS_ALIVE, 0, false),
            array(Client::ATTR_CONNECT_TIMEOUT, 42, 42),
            array(Client::ATTR_FOLLOW_LOCATION, 0, 0),
            array(Client::ATTR_AUTO_REFERER_ON_FOLLOW, 'yes', true),
            array(Client::ATTR_HOST_CONCURRENCY_LIMIT, 1, 1),
            array(Client::ATTR_IO_BUFFER_SIZE, 512, 512),
            array(Client::ATTR_SSL_VERIFY_PEER, 1, true),
            array(Client::ATTR_SSL_ALLOW_SELF_SIGNED, 'no', false),
            array(Client::ATTR_SSL_CA_FILE, '/path1', '/path1'),
            array(Client::ATTR_SSL_CA_PATH, '/path2', '/path2'),
            array(Client::ATTR_SSL_LOCAL_CERT, '/path3', '/path3'),
            array(Client::ATTR_SSL_LOCAL_CERT_PASSPHRASE, 'pass', 'pass'),
            array(Client::ATTR_SSL_CN_MATCH, '*.google.com', '*.google.com'),
            array(Client::ATTR_SSL_VERIFY_DEPTH, 10, 10), 
            array(Client::ATTR_SSL_CIPHERS, 'NOT DEFAULT', 'NOT DEFAULT')
        );
    }
    
    /**
     * @dataProvider provideAttributeValues
     * @covers Artax\Client::setAttribute
     */
    public function testSetAttributeAssignsValue($attribute, $value, $expectedResult) {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $client->setAttribute($attribute, $value);
        $this->assertEquals($expectedResult, $client->getAttribute($attribute));
    }
    
    
    public function provideInvalidMultiRequestLists() {
        return array(
            array(42),
            array(new StdClass),
            array(array(42)),
            array(array())
        );
    }
    
    /**
     * @dataProvider provideInvalidMultiRequestLists
     * @covers Artax\Client::sendMulti
     * @covers Artax\Client::validateRequestList
     * @expectedException Spl\TypeException
     */
    public function testSendMultiThrowsExceptionOnInvalidRequestTraversable($badRequestList) {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $client->sendMulti($badRequestList);
    }
    
    public function provideRequestsInNeedOfNormalization() {
        $return = array();
        
        $request = new StdRequest('http://localhost', 'GET');
        $request->setHeader('Accept-Encoding', 'gzip');
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n";
        $return[] = array($request, $expectedWrite);
        
        
        $request = new StdRequest('http://localhost', 'GET');
        $request->setHeader('Content-Length', 10);
        $request->setHeader('Transfer-Encoding', 'chunked');
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n";
        $return[] = array($request, $expectedWrite);
        
        
        $body = 'test';
        $request = new StdRequest('http://localhost', 'POST');
        $request->setBody($body);
        $request->setHeader('Content-Length', 10);
        $request->setHeader('Transfer-Encoding', 'chunked');
        $expectedWrite = '' .
            "POST / HTTP/1.1\r\n" .
            "Content-Length: ".strlen($body)."\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "$body";
        $return[] = array($request, $expectedWrite);
        
        
        $body = fopen('php://memory', 'r');
        $request = new StdRequest('http://localhost', 'POST');
        $request->setBody($body);
        $request->setHeader('Content-Length', 10);
        $request->setHeader('Transfer-Encoding', 'chunked');
        $expectedWrite = '' .
            "POST / HTTP/1.1\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "0\r\n" .
            "\r\n";
        $return[] = array($request, $expectedWrite);
        
        return $return;
    }
    
    /**
     * @dataProvider provideRequestsInNeedOfNormalization
     * @covers Artax\Client::normalizeRequestHeaders
     */
    public function testSendNormalizesRequestHeaders($request, $expectedWrite) {
        $dummyRawResponseData =  '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 20\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "12345678901234567890"
        ;
        ClientSocketStreamWrapper::$body = $dummyRawResponseData;
        
        // Build a Client that connects to our custom Socket object
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array(new HashingMediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force the Client to add `Connection: close` headers to requests
        $client->setAttribute(Client::ATTR_KEEP_CONNS_ALIVE, false);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 4);
        
        $client->send($request);
        
        $this->assertEquals($expectedWrite, ClientSocketStreamWrapper::$writtenData);
    }
    
    
    
    
    public function provideRequestExpectations() {
        $return = array();
        
        // ------------------------------------------------------------------------
        
        $request = new StdRequest('http://localhost', 'GET');
        $request->setAllHeaders(array(
            'User-Agent' => Client::USER_AGENT,
            'Host' => $request->getAuthority()
        ));
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "\r\n";
        
        $body = "12345678901234567890";
        $md5 = md5($body);
        $rawReturnResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 20\r\n" .
            "Content-MD5: $md5\r\n" .
            "\r\n" .
            "$body";
        
        $response = new ChainableResponse($request->getUri());
        $response->setStartLine("HTTP/1.1 200 OK\r\n");
        $response->setAllHeaders(array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 20,
            'Content-MD5' => $md5
        ));
        
        $body = fopen('php://memory', 'r+');
        fwrite($body, '12345678901234567890');
        rewind($body);
        $response->setBody($body);
        
        $return[] = array($request, $expectedWrite, $rawReturnResponse, $response);
        
        // ------------------------------------------------------------------------
        
        $request = new StdRequest('http://localhost', 'POST');
        $request->setAllHeaders(array(
            'User-Agent' => Client::USER_AGENT,
            'Host' => $request->getAuthority()
        ));
        $body = fopen('php://memory', 'r+');
        fwrite($body, '12345678');
        rewind($body);
        $request->setBody($body);
        
        $expectedWrite = '' .
            "POST / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "4\r\n" .
            "1234" .
            "\r\n" .
            "4\r\n" .
            "5678" .
            "\r\n" .
            "0\r\n" .
            "\r\n";
        $rawReturnResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "done";
        
        $response = new ChainableResponse($request->getUri());
        $response->setStartLine("HTTP/1.1 200 OK\r\n");
        $response->setAllHeaders(array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 4,
        ));
        $response->setBody('done');
        
        $return[] = array($request, $expectedWrite, $rawReturnResponse, $response);
        
        // ------------------------------------------------------------------------
        
        $request = new StdRequest('https://localhost:443', 'GET');
        
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n";
        $rawReturnResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 0\r\n" .
            "\r\n";
        
        $response = new ChainableResponse($request->getUri());
        $response->setStartLine("HTTP/1.1 200 OK\r\n");
        $response->setAllHeaders(array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 0,
        ));
        
        $return[] = array($request, $expectedWrite, $rawReturnResponse, $response);
        
        // ------------------------------------------------------------------------
        
        return $return;
    }
    
    /**
     * @dataProvider provideRequestExpectations
     */
    public function testSend($request, $expectedWrite, $rawReturnResponse, $expectedResponse) {
        ClientSocketStreamWrapper::$body = $rawReturnResponse;
        
        $client = $this->getMock('Artax\Client', array('makeSocket'), array(new HashingMediator));
        
        $scheme = $request->getScheme() == 'http' ? 'tcp' : 'tls';
        $socketUri = $scheme . '://' . $request->getHost() . ':' . $request->getPort();
        $socket = new StubSocket($request->getUri(), 'r+', new Uri($socketUri));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force the Client to read multiple times to receive the full response
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        
        $actualResponse = $client->send($request);
        
        $this->assertEquals($expectedResponse->getStartLine(), $actualResponse->getStartLine());
        $this->assertEquals($expectedResponse->getAllHeaders(), $actualResponse->getAllHeaders());
        $this->assertEquals($expectedResponse->getBody(), $actualResponse->getBody());
    }
    
    /**
     * @dataProvider provideRequestExpectations
     */
    public function testSendMulti($request, $expectedWrite, $rawReturnResponse, $expectedResponse) {
        ClientSocketStreamWrapper::$body = $rawReturnResponse;
        
        $scheme = $request->getScheme() == 'http' ? 'tcp' : 'tls';
        $socketUri = $scheme . '://' . $request->getHost() . ':' . $request->getPort();
        $socket = new StubSocket($request->getUri(), 'r+', new Uri($socketUri));
        
        $client = $this->getMock('Artax\Client', array('makeSocket'), array(new HashingMediator));
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        
        $multiResponse = $client->sendMulti(array($request));
        $actualResponse = $multiResponse->current();
        
        $this->assertEquals($expectedResponse->getStartLine(), $actualResponse->getStartLine());
        $this->assertEquals($expectedResponse->getAllHeaders(), $actualResponse->getAllHeaders());
        $this->assertEquals($expectedResponse->getBody(), $actualResponse->getBody());
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionWhenInfiniteRedirectLoopDetected() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: http://localhost\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $mediator = new HashingMediator;
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $client->send(new StdRequest('http://localhost', 'GET'));
    }
    
    /**
     * @covers Artax\Client::sendMulti
     */
    public function testSendMultiPreventsExceptionFromBubblingUp() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: http://localhost\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $mediator = new HashingMediator;
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $multiResponse = $client->sendMulti(array($request));
        
        $this->assertEquals(1, $multiResponse->getErrorCount());
    }
    
    public function testRedirect() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: http://localhost/redirect\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_REDIRECT, function($key, $old, $new) {
            ClientSocketStreamWrapper::reset();
            ClientSocketStreamWrapper::$body = '' .
                "HTTP/1.1 200 OK\r\n" .
                "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
                "Content-Length: 20\r\n" .
                "\r\n" .
                "12345678901234567890";
        });
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $response = $client->send($request);
        
        $this->assertEquals('http://localhost/redirect', $response->getRequestUri());
    }
    
    /**
     * @covers Artax\Client::send
     * @covers Artax\Client::completeResponse
     * @covers Artax\Client::canRedirect
     * @covers Artax\Client::doRedirect
     */
    public function testRedirectAddsWarningOnInvalidRelativeLocationHeader() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: /redirect\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_REDIRECT, function($key, $old, $new) {
            ClientSocketStreamWrapper::reset();
            ClientSocketStreamWrapper::$body = '' .
                "HTTP/1.1 200 OK\r\n" .
                "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
                "Content-Length: 20\r\n" .
                "\r\n" .
                "12345678901234567890";
        });
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $response = $client->send($request);
        
        $this->assertEquals('http://localhost/redirect', $response->getRequestUri());
        
        $originalResponse = $response->getPreviousResponse();
        $this->assertTrue($originalResponse->hasHeader('Warning'));
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnSocketConnectFailure() {
        $mediator = new HashingMediator;
        
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        ClientSocketStreamWrapper::$failOnOpen = true;
        
        $request = new StdRequest('http://localhost', 'GET');
        $response = $client->send($request);
    }
    
    /**
     * @covers Artax\Client::doMultiSafeSocketCheckout
     */
    public function testSendMultiCatchesExceptionOnSocketConnectFailure() {
        $mediator = new HashingMediator;
        
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        ClientSocketStreamWrapper::$failOnOpen = true;
        
        $request = new StdRequest('http://localhost', 'GET');
        $multiResponse = $client->sendMulti(array($request));
        
        $this->assertEquals(1, $multiResponse->getErrorCount());
        $this->assertInstanceOf('Artax\\ClientException', $multiResponse->current());
    }
    
    /**
     * Mocking write failures cannot be done at the stream wrapper level because all writes
     * through the custom wrapper have their return values cast to an integer. This precludes
     * us from simply having the stream wrapper return FALSE on a write to generate an error.
     * Instead, we need to mock the Socket class so that it will return FALSE independently of 
     * the stream's write result.
     * 
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnSocketWriteFailure() {
        $mediator = new HashingMediator;
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = $this->getMock(
            'StubSocket',
            array('doWrite'),
            array('http://localhost', 'r+', new Uri('tcp://localhost:80'))
        );
        $socket->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(false));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $response = $client->send($request);
    }
    
    /**
     * @covers Artax\Client::doMultiSafeWrite
     */
    public function testSendMultiCatchesExceptionOnSocketWriteFailure() {
        $mediator = new HashingMediator;
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = $this->getMock(
            'StubSocket',
            array('doWrite'),
            array('http://localhost', 'r+', new Uri('tcp://localhost:80'))
        );
        $socket->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(false));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $multiResponse = $client->sendMulti(array($request));
        
        $this->assertEquals(1, $multiResponse->getErrorCount());
        $this->assertInstanceOf('Artax\\ClientException', $multiResponse->current());
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnBufferedBodyWriteFailureToSocket() {
        $this->markTestSkipped();
        return;

        $mediator = new HashingMediator;
        
        // Have the stream wrapper return false on write when we near completion of the entity body
        $bytesWritten = 0;
        $mediator->addListener(Client::EVENT_SOCK_IO_WRITE, function($key, $data, $bytes) use (&$bytesWritten) {
            $bytesWritten += $bytes;
            if ($bytesWritten > 75) {
                ClientSocketStreamWrapper::$failOnWrite = true;
            }
        });
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'POST');
        $request->setBody(
            'When in the chronicle of wasted time ' .
            'I see descriptions of the fairest wights ' .
            'And beauty making beautiful old rhyme ' .
            'In praise of ladies dead and lovely knights '
        );
        $response = $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnContentMd5Mismatch() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 20\r\n" .
            "Content-MD5: some-invalid-md5-hash\r\n" .
            "\r\n" .
            "12345678901234567890";
        
        $mediator = new HashingMediator;
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $response = $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnRequestUriWithNonHttpOrHttpsScheme() {
        $mediator = new HashingMediator;
        $client = new Client($mediator);
        $request = new StdRequest('ws://someurl', 'GET');
        $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnContentLengthMismatch() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 22\r\n" .
            "\r\n" .
            "You'll never see this!";
        
        $mediator = new HashingMediator;
        
        // Simulate a disconnected socket connection after X bytes read
        $bytesRead = 0;
        $totalBytes = strlen(ClientSocketStreamWrapper::$body);
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, function($key, $data, $bytes) use (&$bytesRead, $totalBytes) {
            $bytesRead += $bytes;
            if ($bytesRead > $totalBytes - 10) {
                ClientSocketStreamWrapper::$socketGoneOnRead = true;
            }
        });
        
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        $request = new StdRequest('http://localhost', 'GET');
        $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnTempResponseBodyStreamOpenFailure() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 22\r\n" .
            "\r\n" .
            "You'll never see this!";
        
        $mediator = new HashingMediator;
        
        $stream = $this->getMock('Artax\\Streams\\Stream', array('doOpen'), array('php://temp', 'r+'));
        $stream->expects($this->once())
               ->method('doOpen')
               ->will($this->returnValue(false));
        
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client = $this->getMock('Artax\\Client', array('makeSocket', 'makeResponseBodyStream'), array($mediator));
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        $client->expects($this->once())
               ->method('makeResponseBodyStream')
               ->will($this->returnValue($stream));
        
        $request = new StdRequest('http://localhost', 'GET');
        $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnTempResponseBodyStreamWriteFailure() {
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 22\r\n" .
            "\r\n" .
            "You'll never see this!";
        
        $mediator = new HashingMediator;
        
        $stream = $this->getMock('Artax\\Streams\\Stream', array('doWrite'), array('php://temp', 'r+'));
        $stream->expects($this->once())
               ->method('doWrite')
               ->will($this->returnValue(false));
        
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client = $this->getMock('Artax\\Client', array('makeSocket', 'makeResponseBodyStream'), array($mediator));
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        $client->expects($this->once())
               ->method('makeResponseBodyStream')
               ->will($this->returnValue($stream));
        
        $request = new StdRequest('http://localhost', 'GET');
        $client->send($request);
    }
    
    
    
    public function testSendingRequestWithoutBodyParsesResponseTerminatedByContentLength() {
        $mediator = new HashingMediator();
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force multiple reads to retrieve full response
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        
        $request = new StdRequest('http://localhost', 'GET');
        $request->setAllHeaders(array(
            'User-Agent' => Client::USER_AGENT,
            'Host' => $request->getAuthority(),
            'Connection' => 'close'
        ));
        
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: localhost\r\n" .
            "Connection: close\r\n\r\n"
        ;
        
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 20\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "12345678901234567890"
        ;
        
        $actualRead = '';
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, function($key, $data, $bytes) use (&$actualRead) {
            $actualRead .= $data;
        });
        
        $response = $client->send($request);
        
        $this->assertInstanceOf('Artax\\Http\\Response', $response);
        $this->assertEquals($expectedWrite, ClientSocketStreamWrapper::$writtenData);
        $this->assertEquals(ClientSocketStreamWrapper::$body, $actualRead);
    }
    
    public function testSendingRequestWithBodyParsesChunkedResponse() {
        $mediator = new HashingMediator();
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force multiple reads to retrieve full response
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 4);
        
        $requestBody = 'When in the chronicle of wasted time';
        $request = new StdRequest('http://localhost', 'POST');
        $request->setBody($requestBody);
        
        
        $expectedWrite = '' .
            "POST / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: localhost\r\n" .
            "Content-Length: 36\r\n" .
            "\r\n" .
            "$requestBody"
        ;
        
        ClientSocketStreamWrapper::$body = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Connection: close\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "24\r\n" .
            "$requestBody\r\n" .
            "000000\r\n" .
            "\r\n"
        ;
        
        $actualRead = '';
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, function($key, $data, $bytes) use (&$actualRead) {
            $actualRead .= $data;
        });
        
        $response = $client->send($request);
        
        $this->assertInstanceOf('Artax\\Http\\Response', $response);
        $this->assertEquals($expectedWrite, ClientSocketStreamWrapper::$writtenData);
        $this->assertEquals(ClientSocketStreamWrapper::$body, $actualRead);
        $this->assertEquals($requestBody, $response->getBody());
    }
    
}


class StubSocket extends Stream implements SocketResource {
    protected $uri;
    
    public function __construct($path, $mode, Uri $uri) {
        parent::__construct($path, $mode);
        $this->uri = $uri;
    }
    
    protected function doOpen($path, $mode) {
        if (ClientSocketStreamWrapper::$failOnOpen) {
            return false;
        } else {
            return parent::doOpen($path, $mode);
        }
    }
    
    public function read($bytesToRead) {
        if (ClientSocketStreamWrapper::$failOnRead) {
            return false;
        } elseif (ClientSocketStreamWrapper::$socketGoneOnRead) {
            throw new \Artax\Streams\SocketGoneException();
        } elseif (!is_null(ClientSocketStreamWrapper::$readReturn)){
            return ClientSocketStreamWrapper::$readReturn;
        } else {
            return parent::read($bytesToRead);
        }
    }
    
    public function write($dataToWrite) {
        if (ClientSocketStreamWrapper::$failOnWrite) {
            return false;
        } elseif (!is_null(ClientSocketStreamWrapper::$writeReturn)){
            return ClientSocketStreamWrapper::$writeReturn;
        } else {
            return parent::write($dataToWrite);
        }
    }
    
    public function setConnectTimeout($seconds) {}
    public function setConnectFlags($flagBitmask) {}
    public function setContextOptions(array $options) {}
    public function getActivityTimestamp() {}
    public function getBytesSent(){}
    public function getBytesRecd(){}
    public function isConnected(){}
    public function getScheme(){
        return $this->uri->getScheme();
    }
    public function getHost(){
        return $this->uri->getHost();
    }
    public function getPort(){
        return $this->uri->getPort();
    }
    public function getAuthority(){
        return $this->uri->getAuthority();
    }
    public function getPath(){
        return $this->uri->getPath();
    }
    public function getUri(){
        return $this->uri->__toString();
    }
}

class ClientSocketStreamWrapper {
    
    public $context;
    
    public static $position = 0;
    public static $body = '';
    public static $writtenData = '';
    
    public static $failOnOpen = false;
    public static $readReturn = null;
    public static $writeReturn = null;
    public static $failOnRead = false;
    public static $failOnWrite = false;
    public static $socketGoneOnRead = false;
    public static $ftellReturn = null;
    
    /**
     * Reset mocking flags and aggregation values for each test
     */
    public static function reset() {
        static::$position = 0;
        static::$body = '';
        static::$writtenData = '';
        
        static::$failOnOpen = false;
        static::$readReturn = null;
        static::$failOnRead = false;
        static::$socketGoneOnRead = false;
        static::$writeReturn = null;
        static::$failOnWrite = false;
        static::$ftellReturn = null;
        
    }
    
    /**
     * Mock the return value of the fopen operation on the stream
     */
    public function stream_open($path, $mode, $options, &$opened_path) {
        if (static::$failOnOpen) {
            // Doesn't work for fopen operations -- we must instead check this flag in our socket
            // stub's open method and return false from the stub to mock an fopen failure.
        } else {
            return true;
        }
    }
    
    /**
     * Mock the return value of the fread operations on the stream
     */
    public function stream_read($bytes) {
        if (static::$failOnRead) {
            return false;
        } elseif (!is_null(static::$readReturn)) {
            return static::$readReturn;
        } else {
            $chunk = substr(static::$body, static::$position, $bytes);
            static::$position += strlen($chunk);
            return $chunk;
        }
    }
    
    /**
     * Mock the return value of the fwrite operations on the stream
     */
    public function stream_write($data) {
        if (static::$failOnWrite) {
            // Doesn't work because custom stream wrapper return values from this method are cast 
            // to an integer. As a result, we can't return false from the stream wrapper and this 
            // value must be checked and handled by the stub socket class.
            return false;
        } elseif (is_null(static::$writeReturn)) {
            static::$writtenData .= $data;
            return strlen($data);
        } else {
            return static::$writeReturn;
        }
    }
    
    /**
     * Mock the return value of the ftell operations on the stream
     */
    public function stream_tell() {
        if (is_null(static::$ftellReturn)) {
            return static::$position;
        } else {
            return static::$ftellReturn;
        }
    }
    
    public function stream_close() {}
    public function stream_set_option($option, $arg1, $arg2) {}
    public function stream_eof() {}
}