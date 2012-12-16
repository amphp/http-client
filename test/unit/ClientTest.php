<?php

use Ardent\HashingMediator,
    Artax\Uri,
    Artax\Client,
    Artax\ClientBuilder,
    Artax\ClientResponse,
    Artax\RequestWriterFactory,
    Artax\ResponseParserFactory,
    Artax\Http\StdRequest;

class ClientTest extends PHPUnit_Framework_TestCase {
    
    protected function setUp() {
        SocketStreamWrapper::reset();
        stream_wrapper_register('testing', 'SocketStreamWrapper');
    }
    
    public function tearDown() {
        stream_wrapper_unregister('testing');
    }
    
    /**
     * @expectedException Ardent\KeyException
     */
    public function testSetAttributeThrowsExceptionOnInvalidAttribute() {
        $clientBuilder = new ClientBuilderStub;
        $client = $clientBuilder->build();
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
     */
    public function testSetAttributeAssignsValue($attribute, $value, $expectedResult) {
        $clientBuilder = new ClientBuilderStub;
        $client = $clientBuilder->build();
        $client->setAttribute($attribute, $value);
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
     * @expectedException Ardent\TypeException
     */
    public function testSendMultiThrowsExceptionOnInvalidRequestTraversable($badRequestList) {
        $clientBuilder = new ClientBuilderStub;
        $client = $clientBuilder->build();
        $client->sendMulti($badRequestList);
    }
    
    /**
     * @expectedException Ardent\DomainException
     */
    public function testSendThrowsExceptionOnRequestUriWithoutHttpOrHttpsScheme() {
        $clientBuilder = new ClientBuilderStub;
        $client = $clientBuilder->build();
        $client->send('ws://someurl');
    }
    
    public function provideRequestExpectations() {
        $return = array();
        
        // ------------------------------------------------------------------------
        
        $uri = 'http://localhost';
        $request = new StdRequest;
        $request->setUri($uri);
        $request->setAllHeaders(array(
            'User-Agent' => Client::USER_AGENT,
            'Host' => 'localhost'
        ));
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: localhost\r\n" .
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
        
        $headers = array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 20,
            'Content-MD5' => $md5,
            'Connection' => 'close',
        );
        $body = fopen('data://text/plain;base64,' . base64_encode('12345678901234567890'), 'r');
        
        $valueResponse = new ValueResponse(1.1, 200, 'OK', $headers, $body);
        $clientResult = new ClientResult(array(
            $uri = $valueResponse
        ));
        
        $return[] = array($request, $expectedWrite, $rawReturnResponse, $response);
        
        // ------------------------------------------------------------------------
        
        $request = new StdRequest;
        $uri = 'http://localhost';
        $request->setUri($uri);
        $request->setMethod('POST');
        $request->setAllHeaders(array(
            'User-Agent' => Client::USER_AGENT,
            'Host' => 'localhost'
        ));
        $body = fopen('php://memory', 'r+');
        fwrite($body, '12345678');
        rewind($body);
        $request->setBody($body);
        
        $expectedWrite = '' .
            "POST / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: localhost\r\n" .
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
        
        $headers = array(
            'Date' => 'Sun, 14 Oct 2012 06:00:46 GMT',
            'Content-Length' => 4,
        );
        $body = 'done';
        $valueResponse = new ValueResponse(1.1, 200, 'OK', $headers, $body);
        $clientResponse = new ClientResponse(array(
            $uri => $valueResponse
        ));
        
        
        $return[] = array($request, $expectedWrite, $rawReturnResponse, $clientResponse);
        
        // ------------------------------------------------------------------------
        
        $request ='https://localhost:443';
        
        $expectedWrite = '' .
            "GET / HTTP/1.1\r\n" .
            "User-Agent: ".Client::USER_AGENT."\r\n" .
            "Host: localhost:443\r\n" .
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
     *
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
    */
}



class ClientBuilderStub extends ClientBuilder {
    public function build(\Ardent\Mediator $mediator = null) {
        $writerFactory = new RequestWriterFactory;
        $parserFactory = new ResponseParserFactory;
        $mediator = $mediator ?: new HashingMediator;
        
        return new ClientStub($writerFactory, $parserFactory, $mediator);
    }
}

class ClientStub extends Client {
    protected function makeSocketStream($uri, $context) {
        $socket = fopen('testing://uri', 'r+');
        return array($socket, null, null);
    }
}

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
    
    public function stream_stat() {
        return array();
    }
    
    public function stream_cast($cast_as) {
        
    }
    
    public function stream_eof() {
        return (static::$position == strlen(static::$rawResponse));
    }
    
    public function stream_close() {}
    public function stream_set_option($option, $arg1, $arg2) {}
}