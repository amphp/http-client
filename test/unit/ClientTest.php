<?php

use Spl\HashingMediator,
    Artax\Uri,
    Artax\Client,
    Artax\Http\StdRequest,
    Artax\ChainableResponse;

/**
 * @covers Artax\ClientState
 * @covers Artax\Client
 */
class ClientTest extends PHPUnit_Framework_TestCase {
    
    protected function setUp() {
        SocketStreamWrapper::reset();
        stream_wrapper_register('testing', 'SocketStreamWrapper');
    }
    
    public function tearDown() {
        stream_wrapper_unregister('testing');
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
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnRequestUriWithNonHttpOrHttpsScheme() {
        $client = new Client(new HashingMediator);
        $client->send(new StdRequest('ws://someurl'));
    }
    
    /**
     * @covers Artax\Client::normalizeRequestHeaders
     */
    public function testSendRemovesContentLengthHeaderOnStreamRequestBody() {
        SocketStreamWrapper::setDefaultRawResponse();
        
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
        
        // Build a Client that connects to our custom Socket object
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_KEEP_CONNS_ALIVE, false);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->send($request);
        
        $this->assertEquals($expectedWrite, SocketStreamWrapper::$writtenData);
    }
    
    /**
     * @covers Artax\Client::normalizeRequestHeaders
     */
    public function testSendRemovesTransferEncodingHeaderAndAssignsContentLengthOnBufferedRequestBody() {
        SocketStreamWrapper::setDefaultRawResponse();
        
        $request = new StdRequest('http://localhost', 'POST');
        $body = 'test body';
        $request->setBody($body);
        $request->setHeader('Content-Length', 9999);
        $request->setHeader('Transfer-Encoding', 'chunked');
        $expectedWrite = '' .
            "POST / HTTP/1.1\r\n" .
            "Content-Length: ".strlen($body)."\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "$body";
        
        // Build a Client that connects to our custom Socket object
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_KEEP_CONNS_ALIVE, false);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->send($request);
        
        $this->assertEquals($expectedWrite, SocketStreamWrapper::$writtenData);
    }
    
    /**
     * @covers Artax\Client::normalizeRequestHeaders
     */
    public function testSendRemovesContentLengthAndTransferEncodingHeaderFromRequestIfNoBodyExists() {
        SocketStreamWrapper::setDefaultRawResponse();
        
        $request = new StdRequest('http://localhost');
        $request->setHeader('Content-Length', 10);
        $request->setHeader('Transfer-Encoding', 'chunked');
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n";
        
        // Build a Client that connects to our custom Socket object
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_KEEP_CONNS_ALIVE, false);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->send($request);
        
        $this->assertEquals($expectedWrite, SocketStreamWrapper::$writtenData);
    }
    
    /**
     * @covers Artax\Client::normalizeRequestHeaders
     */
    public function testSendIgnoresRequestBodyOnTraceMethod() {
        SocketStreamWrapper::setDefaultRawResponse();
        
        $request = new StdRequest('http://localhost', 'TRACE');
        $request->setBody('test');
        $expectedWrite = '' .
            "TRACE / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n";
        
        // Build a Client that connects to our custom Socket object
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_KEEP_CONNS_ALIVE, false);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->send($request);
        
        $this->assertEquals($expectedWrite, SocketStreamWrapper::$writtenData);
    }
    
    /**
     * @covers Artax\Client::normalizeRequestHeaders
     */
    public function testSendRemovesAcceptEncodingHeaderFromRequest() {
        SocketStreamWrapper::setDefaultRawResponse();
        
        $request = new StdRequest('http://localhost');
        $request->setHeader('Accept-Encoding', 'gzip');
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: ".$request->getAuthority()."\r\n" .
            "Connection: close\r\n" .
            "\r\n";
        
        // Build a Client that connects to our custom Socket object
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_KEEP_CONNS_ALIVE, false);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->send($request);
        
        $this->assertEquals($expectedWrite, SocketStreamWrapper::$writtenData);
    }
    
    
    public function provideRequestExpectations() {
        $return = array();
        
        // ------------------------------------------------------------------------
        
        $request = new StdRequest('http://localhost');
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
            "Connection: close\r\n" .
            "\r\n" .
            "$body";
        
        $response = new ChainableResponse($request->getUri());
        $response->setStartLine("HTTP/1.1 200 OK\r\n");
        $response->setAllHeaders(array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 20,
            'Content-MD5' => $md5,
            'Connection' => 'close',
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
        
        $request = new StdRequest('https://localhost:443');
        
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
        SocketStreamWrapper::$rawResponse = $rawReturnResponse;
        
        $client = new ClientStub(new HashingMediator);
        $client->setAllAttributes(array(
            Client::ATTR_IO_BUFFER_SIZE => 1,
            Client::ATTR_SSL_CA_PATH => '/somepath',
            Client::ATTR_SSL_LOCAL_CERT => '/somefile',
            Client::ATTR_SSL_LOCAL_CERT_PASSPHRASE => 'somepass'
        ));
        
        $actualResponse = $client->send($request);
        
        $this->assertEquals($expectedResponse->getStartLine(), $actualResponse->getStartLine());
        $this->assertEquals($expectedResponse->getAllHeaders(), $actualResponse->getAllHeaders());
        $this->assertEquals($expectedResponse->getBody(), $actualResponse->getBody());
    }
    
    /**
     * @dataProvider provideRequestExpectations
     */
    public function testSendMulti($request, $expectedWrite, $rawReturnResponse, $expectedResponse) {
        SocketStreamWrapper::$rawResponse = $rawReturnResponse;
        
        $client = new ClientStub(new HashingMediator);
        $client->setAllAttributes(array(
            Client::ATTR_IO_BUFFER_SIZE => 4,
            Client::ATTR_SSL_CA_PATH => '/somepath',
            Client::ATTR_SSL_LOCAL_CERT => '/somefile',
            Client::ATTR_SSL_LOCAL_CERT_PASSPHRASE => 'somepass'
        ));
        
        $multiResponse = $client->sendMulti(array($request));
        $actualResponse = $multiResponse->current();
        
        $this->assertEquals($expectedResponse->getStartLine(), $actualResponse->getStartLine());
        $this->assertEquals($expectedResponse->getAllHeaders(), $actualResponse->getAllHeaders());
        $this->assertEquals($expectedResponse->getBody(), $actualResponse->getBody());
    }
    
    /**
     * @covers Artax\Client::isNewSocketConnectionAllowed
     */
    public function testSendMultiChecksForHostConcurrencyBeforeOpeningNewConnection() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Location: http://localhost\r\n" . // <--- same URI as our request
            "Content-Length: 5\r\n" .
            "Connection: keep-alive\r\n" .
            "\r\n" .
            "Woot!";
        
        $client = new ClientStub(new HashingMediator);
        $client->sendMulti(array(
            new StdRequest('http://localhost'),
            new StdRequest('http://localhost/test1'),
            new StdRequest('http://localhost/test2')
        ));
    }
    
    public function testSendReceivesMessageWithTransferEncodingChunked() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "4\r\n" .
            "woot\r\n" .
            "0\r\n" .
            "\r\n";
        
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $this->assertEquals('woot', $response->getBody());
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnSocketConnectFailure() {
        $client = new Client(new HashingMediator);
        $url = 'http://if-a-domain-name-actually-matches-this-and-causes-the-test-to-fail-omg';
        $response = $client->send(new StdRequest($url));
    }
    
    /**
     * @covers Artax\Client::doMultiSafeSocketCheckout
     */
    public function testSendThrowsExceptionOnSslSocketConnectFailure() {
        SocketStreamWrapper::$failOnOpen = true;
        
        $client = new ClientStubSsl(new HashingMediator);
        $multiResponse = $client->sendMulti(array(
            new StdRequest('http://localhost')
        ));
        
        $this->assertTrue($multiResponse->hasErrors());
        $this->assertInstanceOf('Artax\\ClientException', $multiResponse->current());
    }
    
    /**
     * @covers Artax\Client::doMultiSafeSocketCheckout
     */
    public function testSendMultiCatchesExceptionOnSocketConnectFailure() {
        SocketStreamWrapper::$failOnOpen = true;
        
        $client = new ClientStub(new HashingMediator);
        $multiResponse = $client->sendMulti(array(
            new StdRequest('http://localhost')
        ));
        
        $this->assertTrue($multiResponse->hasErrors());
        $this->assertInstanceOf('Artax\\ClientException', $multiResponse->current());
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnSocketWriteFailure() {
        SocketStreamWrapper::$failOnWrite = true;
        SocketStreamWrapper::$socketIsDead = true;
        
        $client = new ClientStub(new HashingMediator);
        $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @covers Artax\Client::doMultiSafeWrite
     */
    public function testSendMultiCatchesExceptionOnSocketWriteFailure() {
        SocketStreamWrapper::$failOnWrite = true;
        SocketStreamWrapper::$socketIsDead = true;
        
        $client = new ClientStub(new HashingMediator);
        $multiResponse = $client->sendMulti(array(
            new StdRequest('http://localhost')
        ));
        
        $this->assertInstanceOf('Artax\\ClientException', $multiResponse->current());
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnSocketReadFailure() {
        SocketStreamWrapper::$failOnRead = true;
        SocketStreamWrapper::$socketIsDead = true;
        
        $client = new ClientStub(new HashingMediator);
        $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @covers Artax\Client::doMultiSafeRead
     */
    public function testSendMultiCatchesExceptionOnSocketReadFailure() {
        SocketStreamWrapper::$failOnRead = true;
        SocketStreamWrapper::$socketIsDead = true;
        
        $client = new ClientStub(new HashingMediator);
        $multiResponse = $client->sendMulti(array(
            new StdRequest('http://localhost')
        ));
        
        $this->assertInstanceOf('Artax\\ClientException', $multiResponse->current());
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnBufferedRequestBodySocketWriteFailure() {
        $mediator = new HashingMediator;
        
        $bytesWritten = 0;
        $mediator->addListener(Client::EVENT_SOCK_IO_WRITE, function($key, $data, $bytes) use (&$bytesWritten) {
            $bytesWritten += $bytes;
            if ($bytesWritten > 75) {
                SocketStreamWrapper::$failOnWrite = true;
            }
        });
        
        $client = new ClientStub($mediator);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 10);
        
        $request = new StdRequest('http://localhost', 'POST');
        $request->setBody(
            'When in the chronicle of wasted time ' .
            'I see descriptions of the fairest wights ' .
            'And beauty making beautiful old rhyme ' .
            'In praise of ladies dead and lovely knights '
        );
        
        $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnStreamingRequestBodySocketWriteFailure() {
        $mediator = new HashingMediator;
        
        $bytesWritten = 0;
        $mediator->addListener(Client::EVENT_SOCK_IO_WRITE, function($key, $data, $bytes) use (&$bytesWritten) {
            $bytesWritten += $bytes;
            if ($bytesWritten > 75) {
                SocketStreamWrapper::$failOnWrite = true;
            }
        });
        
        $client = new ClientStub($mediator);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 10);
        
        $request = new StdRequest('http://localhost', 'POST');
        $body = fopen('php://memory', 'r+');
        fwrite(
            $body,
            'When in the chronicle of wasted time ' .
            'I see descriptions of the fairest wights ' .
            'And beauty making beautiful old rhyme ' .
            'In praise of ladies dead and lovely knights '
        );
        rewind($body);
        $request->setBody($body);
        
        $client->send($request);
    }
    
    public function provideRequestsWhoseStatusCodePrecludesEntityBody() {
        return array(
            array("HTTP/1.1 204 Something\r\nContent-Length: 4\r\n\r\nbody"),
            array("HTTP/1.1 304 Something\r\nContent-Length: 4\r\n\r\nbody"),
            array("HTTP/1.1 100 Something\r\nContent-Length: 4\r\n\r\nbody")
        );
    }
    
    /**
     * @dataProvider provideRequestsWhoseStatusCodePrecludesEntityBody
     */
    public function testSendCompletesAfterHeadersReadIfStatusCodeProhibitsEntityBody($readData) {
        SocketStreamWrapper::$rawResponse = $readData;
        
        $client = new ClientStub(new HashingMediator);
        $response = $client->send(new StdRequest('http://localhost'));
        $responseBody = $response->getBody();
        
        $this->assertEmpty($responseBody);
    }
    
    public function testSendCompletesAfterHeadersReadIfRequestMadeViaTheHeadMethodVerb() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 3\r\n" .
            "\r\n" .
            "DAN";
        
        $client = new ClientStub(new HashingMediator);
        $response = $client->send(new StdRequest('http://localhost', 'HEAD'));
        
        $this->assertEmpty($response->getBody());
    }
    
    public function testSendReadsUntilConnectionCloseIfNoLengthOrChunkingSpecified() {
        $entity = 'WOOT!';
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "\r\n" .
            "$entity";
        
        // Tell the socket to "disconnect" once the full message is read
        $bytesRead = 0;
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, function ($key, $data, $bytes) use (&$bytesRead) {
            $bytesRead += $bytes;
            if ($bytesRead == strlen(SocketStreamWrapper::$rawResponse)) {
                SocketStreamWrapper::$socketIsDead = true;
                SocketStreamWrapper::$readReturn = '';
            }
        });
        
        $client = new ClientStub($mediator);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $expectedLength = strlen($entity);
        $actualLength = strlen($response->getBody());
        
        $this->assertEquals($expectedLength, $actualLength);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnContentLengthMismatch() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 22\r\n" .
            "\r\n" .
            "You'll never see this!";
        
        $mediator = new HashingMediator;
        
        // Simulate a disconnected socket connection after X bytes read
        $bytesRead = 0;
        $failurePoint = strlen(SocketStreamWrapper::$rawResponse) - 5;
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, function($key, $data, $bytes) use (&$bytesRead, $failurePoint) {
            $bytesRead += $bytes;
            
            if ($bytesRead >= $failurePoint) {
                SocketStreamWrapper::$readReturn = '';
                SocketStreamWrapper::$socketIsDead = true;
            }
        });
        
        $client = new ClientStub($mediator);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $response = $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnContentMd5Mismatch() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 20\r\n" .
            "Content-MD5: some-invalid-md5-hash\r\n" . // <--- note the invalid md5 hash
            "\r\n" .
            "12345678901234567890";
        
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnTempResponseBodyStreamWriteFailure() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 22\r\n" .
            "\r\n" .
            "You'll never see this!";
        
        $failurePoint = strlen(SocketStreamWrapper::$rawResponse) - 5;
        $bytesRead = 0;
        $streamWriteFailer = function($key, $data, $bytes) use (&$bytesRead, $failurePoint) {
            $bytesRead += $bytes;
            if ($bytesRead >= $failurePoint) {
                SocketStreamWrapper::$failOnWrite = true;
            }
        };
        
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, $streamWriteFailer);
        
        $client = $this->getMock(
            'ClientStub',
            array('makeTempResponseBodyStream'),
            array($mediator)
        );
        
        $responseBodyStream = fopen('testing://tempbody', 'r+');
        
        $client->expects($this->once())
               ->method('makeTempResponseBodyStream')
               ->will($this->returnValue($responseBodyStream));
        
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        
        $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionIfSocketDisconnectsBeforeFinalChunkOnChunkedEncoding() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "4\r\n" .
            "woot\r\n" .
            "0\r\n" .
            "\r\n";
        
        $mediator = new HashingMediator;
        
        // Simulate a disconnected socket connection after X bytes read
        $bytesRead = 0;
        $failurePoint = strlen(SocketStreamWrapper::$rawResponse) - 5;
        $mediator->addListener(Client::EVENT_SOCK_IO_READ, function($key, $data, $bytes) use (&$bytesRead, $failurePoint) {
            $bytesRead += $bytes;
            
            if ($bytesRead >= $failurePoint) {
                SocketStreamWrapper::$readReturn = '';
                SocketStreamWrapper::$socketIsDead = true;
            }
        });
        
        $client = new ClientStub($mediator);
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $response = $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnTempResponseBodyStreamOpenFailure() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 22\r\n" .
            "\r\n" .
            "You'll never see this!";
        
        $client = $this->getMock(
            'ClientStub',
            array('makeTempResponseBodyStream'),
            array(new HashingMediator)
        );
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        $client->expects($this->once())
               ->method('makeTempResponseBodyStream')
               ->will($this->returnValue(false));
        
        $client->send(new StdRequest('http://localhost'));
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnRequestBodyStreamReadFailure() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Location: http://localhost\r\n" . // <--- same URI as our request
            "Content-Length: 5\r\n" .
            "\r\n" .
            "Woot!";
        
        $request = new StdRequest('http://localhost');
        $body = fopen('testing://memory', 'r+');
        $request->setBody($body);
        
        $client = $this->getMock(
            'ClientStub',
            array('readChunkFromStreamRequestBody'),
            array(new HashingMediator)
        );
        $client->expects($this->once())
               ->method('readChunkFromStreamRequestBody')
               ->will($this->returnValue(false));
        
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 1);
        
        $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnHeaderOverflowWriteToResponseBodyStreamFailure() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Location: http://localhost\r\n" . // <--- same URI as our request
            "Content-Length: 5\r\n" .
            "\r\n" .
            "Woot!";
        
        $request = new StdRequest('http://localhost');
        
        $client = $this->getMock(
            'ClientStub',
            array('writeToTempResponseBodyStream'),
            array(new HashingMediator)
        );
        $client->expects($this->once())
               ->method('writeToTempResponseBodyStream')
               ->will($this->returnValue(false));
        
        $client->send($request);
    }
    
    /**
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionWhenInfiniteRedirectLoopDetected() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: http://localhost\r\n" . // <--- same URI as our request
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $client = new ClientStub(new HashingMediator);
        $client->send(new StdRequest('http://localhost'));
    }
    
    public function testSendMultiCatchesExceptionWhenInfiniteRedirectLoopDetected() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: http://localhost\r\n" . // <--- same URI as our request
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $client = new ClientStub(new HashingMediator);
        $multiResponse = $client->sendMulti(array(
            new StdRequest('http://localhost')
        ));
        
        $this->assertTrue($multiResponse->hasErrors());
    }
    
    /**
     * @covers Artax\Client::send
     * @covers Artax\Client::completeResponse
     * @covers Artax\Client::canRedirect
     * @covers Artax\Client::doRedirect
     * 
     * @expectedException Artax\ClientException
     */
    public function testSendThrowsExceptionOnNestedInfiniteRedirectLoop() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: /redirect1\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $redirectCount = 0;
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_REDIRECT, function($key, $old, $new) use (&$redirectCount) {
            ++$redirectCount;
            SocketStreamWrapper::reset();
            
            if ($redirectCount == 1) {
                SocketStreamWrapper::$rawResponse = '' .
                    "HTTP/1.1 301 Moved Permanently\r\n" .
                    "Location: http://localhost\r\n" . // <--- Points back at the original URI
                    "Content-Length: 6\r\n" .
                    "\r\n" .
                    "Moved!";
            } else {
                SocketStreamWrapper::$rawResponse = '' .
                    "HTTP/1.1 200 OK\r\n" .
                    "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
                    "Content-Length: 20\r\n" .
                    "\r\n" .
                    "12345678901234567890";
            }
        });
        
        $client = new ClientStub($mediator);
        $client->send(new StdRequest('http://localhost'));
    }
    
    public function provideFollowLocationScenariosThatShouldNotRedirect() {
        $return = array();
        
        // ------------------------------------------------------------------------
        
        $attribute = Client::FOLLOW_LOCATION_NONE;
        $rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Location: http://localhost/redirect\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "Moved"
        ;
        $return[] = array($attribute, $rawResponse, new StdRequest('http://localhost'));
        
        // ------------------------------------------------------------------------
        
        $attribute = Client::FOLLOW_LOCATION_ON_3XX;
        $rawResponse = '' .
            "HTTP/1.1 201 Created\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Location: http://localhost/redirect\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "Moved"
        ;
        $return[] = array($attribute, $rawResponse, new StdRequest('http://localhost'));
        
        // ------------------------------------------------------------------------
        
        $attribute = Client::FOLLOW_LOCATION_ON_2XX;
        $rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Location: http://localhost/redirect\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "Moved"
        ;
        $return[] = array($attribute, $rawResponse, new StdRequest('http://localhost'));
        
        // ------------------------------------------------------------------------
        
        $attribute = Client::FOLLOW_LOCATION_ON_3XX;
        $rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Location: http://localhost/redirect\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "Moved"
        ;
        $return[] = array($attribute, $rawResponse, new StdRequest('http://localhost', 'POST'));
        
        // ------------------------------------------------------------------------
        
        return $return;
    }
    
    /**
     * @dataProvider provideFollowLocationScenariosThatShouldNotRedirect
     */
    public function testRedirectDoesntHappenIfPreventedByFollowLocationAttribute(
        $attribute,
        $rawResponse,
        $request
    ) {
        SocketStreamWrapper::$rawResponse = $rawResponse;
        
        $client = new ClientStub(new HashingMediator);
        $client->setAttribute(Client::ATTR_FOLLOW_LOCATION, $attribute);
        $response = $client->send($request);
        
        $this->assertFalse($response->hasPreviousResponse());
    }
    
    public function testRedirect() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: http://localhost/redirect\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_REDIRECT, function($key, $old, $new) {
            SocketStreamWrapper::reset();
            SocketStreamWrapper::$rawResponse = '' .
                "HTTP/1.1 200 OK\r\n" .
                "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
                "Content-Length: 20\r\n" .
                "\r\n" .
                "12345678901234567890";
        });
        
        $client = new ClientStub($mediator);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $this->assertEquals('http://localhost/redirect', $response->getRequestUri());
    }
    
    /**
     * @covers Artax\Client::send
     * @covers Artax\Client::completeResponse
     * @covers Artax\Client::canRedirect
     * @covers Artax\Client::doRedirect
     */
    public function testRedirectAddsWarningOnInvalidRelativeLocationHeader() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 301 Moved Permanently\r\n" .
            "Location: /redirect1\r\n" .
            "Content-Length: 6\r\n" .
            "\r\n" .
            "Moved!";
        
        $redirectCount = 0;
        $mediator = new HashingMediator;
        $mediator->addListener(Client::EVENT_REDIRECT, function($key, $old, $new) use (&$redirectCount) {
            ++$redirectCount;
            SocketStreamWrapper::reset();
            
            if ($redirectCount == 1) {
                SocketStreamWrapper::$rawResponse = '' .
                    "HTTP/1.1 301 Moved Permanently\r\n" .
                    "Location: /redirect2\r\n" .
                    "Content-Length: 6\r\n" .
                    "\r\n" .
                    "Moved!";
            } else {
                SocketStreamWrapper::$rawResponse = '' .
                    "HTTP/1.1 200 OK\r\n" .
                    "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
                    "Content-Length: 20\r\n" .
                    "\r\n" .
                    "12345678901234567890";
            }
        });
        
        $client = new ClientStub($mediator);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $originalResponse = $response->getPreviousResponse();
        $this->assertTrue($originalResponse->hasHeader('Warning'));
        $this->assertEquals('http://localhost/redirect2', $response->getRequestUri());
    }
    
    /**
     * @covers Artax\Client::closeAllSockets
     */
    public function testCloseAllSockets() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "done";
        
        $client = new ClientStub(new HashingMediator);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $this->assertEquals(1, $client->closeAllSockets());
    }
    
    /**
     * @covers Artax\Client::closeSocketsByHost
     */
    public function testCloseSocketsByHost() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "done";
        
        $client = new ClientStub(new HashingMediator);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $this->assertEquals(0, $client->closeSocketsByHost('remotehost'));
        $this->assertEquals(1, $client->closeSocketsByHost('localhost'));
    }
    
    /**
     * @covers Artax\Client::closeIdleSockets
     */
    public function testCloseIdleSockets() {
        SocketStreamWrapper::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "done";
        
        $client = new ClientStub(new HashingMediator);
        $response = $client->send(new StdRequest('http://localhost'));
        
        $this->assertEquals(1, $client->closeIdleSockets(0));
    }
    
}



/**
 * Allows us to customize socket and stream behavior for testing purposes in conjunction with
 * public static values from the SocketStreamWrapper.
 */
class ClientStub extends Client {
    
    protected function makeSocket(Uri $socketUri, $context) {
        if (SocketStreamWrapper::$failOnOpen) {
            return array(false, 42, 'test error message');
        } else {
            $socket = fopen('testing://' . $socketUri->getAuthority(), 'r+');
            return array($socket, null, null);
        }
    }
    
    protected function doSockWrite($socket, $dataToWrite) {
        if (SocketStreamWrapper::$failOnWrite) {
            return false;
        } elseif (!is_null(SocketStreamWrapper::$writeReturn)){
            return SocketStreamWrapper::$writeReturn;
        } else {
            return parent::doSockWrite($socket, $dataToWrite);
        }
    }
    
    protected function doSockRead($bytesToRead) {
        if (SocketStreamWrapper::$failOnRead) {
            return array(false, 0);
        } elseif (!is_null(SocketStreamWrapper::$readReturn)){
            $len = strlen(SocketStreamWrapper::$readReturn);
            return array(SocketStreamWrapper::$readReturn, $len);
        } else {
            return parent::doSockRead($bytesToRead);
        }
    }
    
    protected function isSocketAlive($socket) {
        if (!is_null(SocketStreamWrapper::$socketIsDead)) {
            return !SocketStreamWrapper::$socketIsDead;
        } else {
            return parent::isSocketAlive($socket);
        }
    }
    
    protected function writeToTempResponseBodyStream($stream, $dataToWrite) {
        if (SocketStreamWrapper::$failOnWrite) {
            return false;
        } else {
            return parent::writeToTempResponseBodyStream($stream, $dataToWrite);
        }
    }
}

/**
 * One-off stub class for mocking native error info on SSL connect failure
 */
class ClientStubSsl extends ClientStub {
    private $count = 0;
    protected function nativeOpenSslErrorSeam() {
        if ($this->count > 1) {
            return false;
        }
        ++$this->count;
        return 'test ssl error messag';
    }
}

/**
 * Allows us to control the behavior of socket streams for testing.
 * 
 * This wrapper allows us to test most stream interactions. Some behavior still requires the
 * mocking of Client methods to achieve full test coverage, however.
 */
class SocketStreamWrapper {
    
    public $context;
    
    public static $position = 0;
    public static $rawResponse = '';
    public static $writtenData = '';
    
    public static $failOnOpen = false;
    public static $readReturn = null;
    public static $writeReturn = null;
    public static $failOnRead = false;
    public static $failOnWrite = false;
    public static $throwOnRead = null;
    public static $ftellReturn = null;    
    public static $socketIsDead = null;
    
    /**
     * Reset mocking flags and aggregation values for each test
     */
    public static function reset() {
        static::$position = 0;
        static::$rawResponse = '';
        static::$writtenData = '';
        
        static::$failOnOpen = false;
        static::$readReturn = null;
        static::$failOnRead = false;
        static::$throwOnRead = null;
        static::$writeReturn = null;
        static::$failOnWrite = false;
        static::$ftellReturn = null;
        static::$socketIsDead = null;
    }
    
    public static function setDefaultRawResponse() {
        static::$rawResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 3\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "123"
        ;
    }
    
    /**
     * Mock the return value of the fopen operation on the stream
     */
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    /**
     * Mock the return value of fread operations on the stream
     */
    public function stream_read($bytes) {
        $chunk = substr(static::$rawResponse, static::$position, $bytes);
        static::$position += strlen($chunk);
        return $chunk;
    }
    
    /**
     * Mock the return value of the fwrite operations on the stream
     */
    public function stream_write($data) {
        static::$writtenData .= $data;
        return strlen($data);
    }
    
    public function stream_seek($offset, $whence) {
        switch ($whence) {
            case SEEK_SET:
                static::$position = $offset;
                return true;
                break;
            case SEEK_CUR:
                static::$position += $offset;
                return true;
                break;
            case SEEK_END:
                static::$position = strlen(static::$rawResponse);
                return true;
                break;
            default:
                return false;
        }
    }

    public function stream_tell() {
        return static::$position;
    }
    
    public function stream_eof() {
        return (static::$position == strlen(static::$rawResponse));
    }
    
    public function stream_close() {}
    public function stream_set_option($option, $arg1, $arg2) {}
}

class MemStreamWrapper {
    
    public $context;
    
    public $position = 0;
    public $rawResponse = 'test';
    
    /**
     * Mock the return value of the fopen operation on the stream
     */
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    /**
     * Mock the return value of fread operations on the stream
     */
    public function stream_read($bytes) {
        $chunk = substr($this->rawResponse, $this->position, $bytes);
        $this->position += strlen($chunk);
        return $chunk;
    }
    
    /**
     * Mock the return value of the fwrite operations on the stream
     */
    public function stream_write($data) {
        return strlen($data);
    }
    
    public function stream_seek($offset, $whence) {
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                return true;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                return true;
                break;
            case SEEK_END:
                $this->position = strlen($this->rawResponse);
                return true;
                break;
            default:
                return false;
        }
    }

    public function stream_tell() {
        return $this->position;
    }
    
    public function stream_eof() {
        return ($this->position == strlen($this->rawResponse));
    }
    
    public function stream_close() {}
    public function stream_set_option($option, $arg1, $arg2) {}
}