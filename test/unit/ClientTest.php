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
        stream_wrapper_register('http', 'ClientSocketStreamWrapper');
        ClientSocketStreamWrapper::reset();
    }
    
    public function tearDown() {
        ClientSocketStreamWrapper::reset();
        stream_wrapper_restore('http');
    }
    
    /**
     * @expectedException Spl\ValueException
     */
    public function testSetAttributeThrowsExceptionOnInvalidAttribute() {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $client->setAttribute('some attribute that doesnt exist', true);
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
        $rawReturnResponse = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Date: Sun, 14 Oct 2012 06:00:46 GMT\r\n" .
            "Content-Length: 20\r\n" .
            "\r\n" .
            "12345678901234567890";
        
        $response = new ChainableResponse($request->getUri());
        $response->setStartLine("HTTP/1.1 200 OK\r\n");
        $response->setAllHeaders(array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 20,
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
        
        return $return;
        
        
        
        $body = fopen('php://memory', 'r+');
        fwrite($body, '12345678');
        rewind($body);
        $request = new StdRequest('http://localhost', 'POST');
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
        $return[] = array($request, $expectedWrite);
        
    }
    
    /**
     * @dataProvider provideRequestExpectations
     */
    public function testSend($request, $expectedWrite, $rawReturnResponse, $expectedResponse) {
        ClientSocketStreamWrapper::$body = $rawReturnResponse;
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array(new HashingMediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force the Client to read multiple times to receive the full response
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 2);
        
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
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array(new HashingMediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force the Client to read multiple times to receive the full response
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 4);
        
        $multiResponse = $client->sendMulti(array($request));
        $actualResponse = $multiResponse->current();
        
        $this->assertEquals($expectedResponse->getStartLine(), $actualResponse->getStartLine());
        $this->assertEquals($expectedResponse->getAllHeaders(), $actualResponse->getAllHeaders());
        $this->assertEquals($expectedResponse->getBody(), $actualResponse->getBody());
    }
    
    /**
     * 
     */
    public function testSendingRequestWithoutBodyParsesResponseTerminatedByContentLength() {
        $mediator = new HashingMediator();
        
        $client = $this->getMock('Artax\\Client', array('makeSocket'), array($mediator));
        $socket = new StubSocket('http://localhost', 'r+', new Uri('tcp://localhost:80'));
        $client->expects($this->once())
               ->method('makeSocket')
               ->will($this->returnValue($socket));
        
        // Force multiple reads to retrieve full response
        $client->setAttribute(Client::ATTR_IO_BUFFER_SIZE, 4);
        
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
    function __construct($path, $mode, Uri $uri) {
        parent::__construct($path, $mode);
        $this->uri = $uri;
    }
    function setConnectTimeout($seconds) {}
    function setConnectFlags($flagBitmask) {}
    function setContextOptions(array $options) {}
    function getActivityTimestamp() {}
    function getBytesSent(){}
    function getBytesRecd(){}
    function isConnected(){}
    function getScheme(){
        return $this->uri->getScheme();
    }
    function getHost(){
        return $this->uri->getHost();
    }
    function getPort(){
        return $this->uri->getPort();
    }
    function getAuthority(){
        return $this->uri->getAuthority();
    }
    function getPath(){
        return $this->uri->getPath();
    }
    function getUri(){
        return $this->uri->__toString();
    }
}

class ClientSocketStreamWrapper {
    
    public $context;
    
    public static $position = 0;
    public static $body = '';
    public static $openReturn = null;
    public static $readReturn = null;
    public static $writeReturn = null;
    public static $eofReturn = null;
    public static $writtenData = '';
    
    public static function reset() {
        static::$position = 0;
        static::$body = '';
        static::$openReturn = null;
        static::$readReturn = null;
        static::$writeReturn = null;
        static::$eofReturn = null;
        static::$writtenData = '';
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        if (is_null(static::$openReturn)) {
            return true;
        } else {
            return static::$openReturn;
        }
    }
    
    public function stream_close() {}

    public function stream_read($bytes) {
        if (!is_null(static::$readReturn)) {
            return static::$readReturn;
        } else {
            $chunk = substr(static::$body, static::$position, $bytes);
            static::$position += strlen($chunk);
            return $chunk;
        }
    }
    
    public function stream_write($data) {
        static::$writtenData .= $data;
        
        if (is_null(static::$writeReturn)) {
            return strlen($data);
        } else {
            return static::$writeReturn;
        }
    }

    public function stream_eof() {
        if (is_null(static::$eofReturn)) {
            return static::$position >= strlen(static::$body);
        } else {
            return static::$eofReturn;
        }
    }
    
    public function stream_tell() {
        return static::$position;
    }
    
    public function stream_set_option($option, $arg1, $arg2) {}
}



