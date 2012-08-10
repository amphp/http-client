<?php

use Artax\Http\Client,
    Artax\Http\StdRequest,
    Artax\Http\Response,
    Artax\Http\MutableStdResponse;

class ClientTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::isOpenSslLoaded
     * @covers Artax\Http\Client::getAcceptedEncodings
     * @covers Artax\Http\Client::setMaxRedirects
     */
    public function testSetMaxRedirectsAssignsValueAndReturnsNull() {
        $client = new Client;
        $this->assertNull($client->setMaxRedirects(99));
    }
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::isOpenSslLoaded
     * @covers Artax\Http\Client::getAcceptedEncodings
     * @covers Artax\Http\Client::setTimeout
     */
    public function testSetTimeoutAssignsValueAndReturnsNull() {
        $client = new Client;
        $this->assertNull($client->setTimeout(99));
    }
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::isOpenSslLoaded
     * @covers Artax\Http\Client::getAcceptedEncodings
     * @covers Artax\Http\Client::setProxyStyle
     */
    public function testSetProxyStyleAssignsValueAndReturnsNull() {
        $client = new Client;
        $this->assertNull($client->setProxyStyle('on'));
    }
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::isOpenSslLoaded
     * @covers Artax\Http\Client::getAcceptedEncodings
     * @covers Artax\Http\Client::setSslOptions
     */
    public function testSetSslOptionsAssignsValueAndReturnsNull() {
        $client = new Client;
        $opt = array('verify_peer' => true, 'allow_self_signed' => true);
        $this->assertNull($client->setSslOptions($opt));
    }
    
    /**
     * @covers Artax\Http\Client::__construct
     * @covers Artax\Http\Client::isOpenSslLoaded
     * @covers Artax\Http\Client::getAcceptedEncodings
     * @covers Artax\Http\Client::allowNonStandardRedirects
     */
    public function testAllowNonStandardRedirectsAssignsValueAndReturnsNull() {
        $client = new Client;
        $this->assertNull($client->allowNonStandardRedirects('on'));
    }
    
    /**
     * @covers Artax\Http\Client::normalizeRequestHeaders
     */
    public function testNormalizeRequestAddsChunkedTransferEncodingHeaderOnStreamBody() {
        /*$client = $this->getMock('Artax\\Http\\Client', null, array(
            'request',
            'doRequest',
            ''
        ));
        */
        $bodyStream = fopen('php://memory', 'w+');
        fwrite($bodyStream, 'When in the chronicle of wasted time');
        rewind($bodyStream);
        
        $request = new StdRequest('http://localhost', 'POST', array(), $bodyStream);
        $client = new Client;
        $normalized = $client->normalizeRequestHeaders($request);
        $this->assertInstanceOf('Artax\\Http\\Request', $normalized);
        $this->assertEquals('chunked', $normalized->getHeader('Transfer-Encoding'));
        
        fclose($bodyStream);
    }
    
    /**
     * @covers Artax\Http\Client::normalizeRequestHeaders
     */
    public function testNormalizeRequestAddsContentLengthHeaderOnNonStreamBody() {
        $body = 'body';
        $request = new StdRequest('http://localhost', 'POST', array(), $body);
        $client = new Client;
        $normalized = $client->normalizeRequestHeaders($request);
        $this->assertEquals(strlen($body), $normalized->getHeader('Content-Length'));
    }
    
    /**
     * @covers Artax\Http\Client::normalizeRequestHeaders
     * @covers Artax\Http\Client::getAcceptedEncodings
     */
    public function testNormalizeRequestReplacesUserAgentAndAcceptEncodingHeaders() {
        $request = new StdRequest('http://localhost', 'POST');
        $client = new Client;
        $normalized = $client->normalizeRequestHeaders($request);
        $this->assertEquals('Artax-Http/0.1 (PHP5.3+)', $normalized->getHeader('User-Agent'));
        
        $acceptedEncodings = $client->getAcceptedEncodings();
        $this->assertEquals($acceptedEncodings, $normalized->getHeader('Accept-Encoding'));
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::normalizeRequestHeaders
     * @covers Artax\Http\Client::getSocketUriFromRequest
     * @covers Artax\Http\Client::openConnection
     * @covers Artax\Http\Client::writeRequestToStream
     * @covers Artax\Http\Client::readResponseHeadersFromStream
     * @covers Artax\Http\Client::statusCodeAllowsEntityBody
     * @covers Artax\Http\Client::readEntityBodyFromStream
     * @covers Artax\Http\Client::canRedirect
     * @covers Artax\Http\Client::readEntityBodyWithContentLength
     * @covers Artax\Http\Client::buildRawRequestHeaders
     * @covers Artax\Http\Client::isChunked
     * @covers Artax\Http\Client::writeStringDataToStream
     * @covers Artax\Http\Client::makeStreamFromSocketUri
     */
    public function testRequestReturnsResponse() {
        // Register a custom stream wrapper so we can control the responses our requests receive
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        // Build a custom HTTP response message for the stubbed stream wrapper to return
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine('HTTP/1.1 200 OK');
        $body = 'response body';
        $responseMsg->setBody($body);
        $responseMsg->setAllHeaders(array(
            'Content-Length' => strlen($body)
        ));
        
        CustomStreamWrapper::setBody($responseMsg);
        
        $client = new ClientTcpStub();
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
        $this->assertInstanceOf('Artax\\Http\\Response', $response);
        $this->assertEquals($responseMsg->getBody(), $response->getBody());
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::shouldCloseConnection
     * @covers Artax\Http\Client::closeConnection
     * @covers Artax\Http\Client::readEntityBodyWithContentLength
     * @covers Artax\Http\Client::buildRawRequestHeaders
     * @covers Artax\Http\Client::isChunked
     * @covers Artax\Http\Client::writeStringDataToStream
     * @covers Artax\Http\Client::makeStreamFromSocketUri
     */
    public function testRequestClosesConnectionAfterCompletionIfRequestHeaderIndicatesNeed() {
        // Register a custom stream wrapper so we can control the responses our requests receive
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        // Build a custom HTTP response message for the stubbed stream wrapper to return
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine('HTTP/1.1 200 OK');
        $body = 'response body';
        $responseMsg->setBody($body);
        $responseMsg->setAllHeaders(array(
            'Content-Length' => strlen($body),
            'Connection'     => 'close'
        ));
        
        CustomStreamWrapper::setBody($responseMsg);
        
        $client = $this->getMock('ClientTcpStub', array('closeConnection'));
        $client->expects($this->once())
               ->method('closeConnection')
               ->with($this->isType('resource'));
        
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
        $this->assertInstanceOf('Artax\\Http\\Response', $response);
        $this->assertEquals($responseMsg->getBody(), $response->getBody());
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::shouldCloseConnection
     * @covers Artax\Http\Client::closeConnection
     * @covers Artax\Http\Client::readEntityBodyWithContentLength
     * @covers Artax\Http\Client::buildRawRequestHeaders
     * @covers Artax\Http\Client::isChunked
     * @covers Artax\Http\Client::readEntityBodyFromStream
     * @covers Artax\Http\Client::writeStringDataToStream
     * @covers Artax\Http\Client::makeStreamFromSocketUri
     */
    public function testRequestDecodesCompressedResponseEntityBody() {
        // Register a custom stream wrapper so we can control the responses our requests receive
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        // Build a custom HTTP response message for the stubbed stream wrapper to return
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine('HTTP/1.1 200 OK');
        $body = 'encode test response body';
        $responseMsg->setBody($body);
        $responseMsg->setAllHeaders(array(
            'Content-Length'   => strlen($body),
            'Content-Encoding' => 'gzip'
        ));
        
        CustomStreamWrapper::setBody($responseMsg);
        
        $client = $this->getMock('ClientTcpStub', array('decodeEntityBody'));
        $client->expects($this->once())
               ->method('decodeEntityBody')
               ->with($this->isType('string'), $this->isType('string'))
               ->will($this->returnValue('encode test'));
        
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
        $this->assertEquals('encode test', $response->getBody());
    }
    
    public function provideStatusCodesThatDontAllowEntityBody() {
        return array(
            array(204, 'No Content'),
            array(304, 'Not Modified'),
            array(100, 'Continue'),
            array(199, 'Something')
        );
    }
    
    /**
     * @dataProvider provideStatusCodesThatDontAllowEntityBody
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::readEntityBodyWithContentLength
     * @covers Artax\Http\Client::readResponseHeadersFromStream
     * @covers Artax\Http\Client::statusCodeAllowsEntityBody
     */
    public function testRequestStopsProcessingResponseAfterHeadersForStatusCodesDisallowingBody(
        $statusCode, $statusDescription
    ) {
        // Register a custom stream wrapper so we can control the responses our requests receive
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        // Build a custom HTTP response message for the stubbed stream wrapper to return
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine("HTTP/1.1 $statusCode $statusDescription\r\n\r\n");
        
        CustomStreamWrapper::setBody($responseMsg);
        
        $client = new ClientTcpStub();
        
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
        $this->assertEquals('', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::readEntityBodyFromStream
     * @covers Artax\Http\Client::readChunkedEntityBody
     * @expectedException Artax\Http\Exceptions\TransferException
     */
    public function testRequestThrowsExceptionOnFailedChunkedEntityBodyRead() {
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine("HTTP/1.1 200 OK");
        $unchunkedBody = 'chunked response body';
        $chunkLen = dechex(strlen($unchunkedBody));
        $chunkedBody = "$chunkLen\r\n$unchunkedBody\r\n0\r\n\r\n";
        
        $responseMsg->setBody($chunkedBody);
        $responseMsg->setAllHeaders(array(
            'Transfer-Encoding' => 'chunked'
        ));
        
        CustomStreamWrapper::reset();
        CustomStreamWrapper::setBody($chunkedBody);
        CustomStreamWrapper::$falseOnRead = true;
        
        $client = $this->getMock('ClientTcpStub', array('readResponseHeadersFromStream'));
        $client->expects($this->once())
               ->method('readResponseHeadersFromStream')
               ->will($this->returnValue($responseMsg));
        
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::readEntityBodyFromStream
     * @covers Artax\Http\Client::readChunkedEntityBody
     * @covers Artax\Http\Client::isChunked
     */
    public function testRequestReadsChunkedEntityBody() {
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine("HTTP/1.1 200 OK");
        $unchunkedBody = 'chunked response body';
        $chunkLen = dechex(strlen($unchunkedBody));
        $chunkedBody = "$chunkLen\r\n$unchunkedBody\r\n0\r\n\r\n";
        
        $responseMsg->setBody($chunkedBody);
        $responseMsg->setAllHeaders(array(
            'Transfer-Encoding' => 'chunked'
        ));
        
        CustomStreamWrapper::reset();
        CustomStreamWrapper::setBody($responseMsg);
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $client = new ClientTcpStub();
        $response = $client->request($request);
        $this->assertEquals($unchunkedBody, $response->getBody());
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::readResponseHeadersFromStream
     * @expectedException Artax\Http\Exceptions\NoResponseException
     */
    public function testRequestThrowsExceptionOnNoResponse() {
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        CustomStreamWrapper::$falseOnRead = true;
        
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $client = new ClientTcpStub();
        $response = $client->request($request);
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::openConnection
     * @covers Artax\Http\Client::makeStreamFromSocketUri
     * @expectedException Artax\Http\Exceptions\ConnectException
     */
    public function testRequestThrowsExceptionOnNoResponseToConnectionRequest() {
        $request = new StdRequest('http://localhost', 'GET');
        
        $client = $this->getMock('ClientTcpStub', array('getTcpStream'));
        $client->expects($this->once())
               ->method('getTcpStream')
               ->will($this->returnValue(array(false, 0, 'error')));
        
        $response = $client->request($request);
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::openConnection
     * @covers Artax\Http\Client::makeStreamFromSocketUri
     * @expectedException RuntimeException
     */
    public function testRequestThrowsExceptionOnSslRequestWithoutSslSupport() {
        $request = new StdRequest('https://localhost', 'GET');
        
        $client = $this->getMock('ClientTcpStub', array('isOpenSslLoaded'), array());
        $client->expects($this->once())
               ->method('isOpenSslLoaded')
               ->will($this->returnValue(false));
        
        $response = $client->request($request);
    }
    
    /**
     * @covers Artax\Http\Client::request
     * @covers Artax\Http\Client::doRequest
     * @covers Artax\Http\Client::readEntityBodyWithContentLength
     * @covers Artax\Http\Client::readResponseHeadersFromStream
     * @expectedException Artax\Http\Exceptions\MessageParseException
     */
    public function testRequestThrowsExceptionOnInvalidResponseMessageFromRemoteServer() {
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        $invalidHttpMessage = "HTTP/1.1 200 OK\r\nInvalidHeaderPattern";
        CustomStreamWrapper::setBody($invalidHttpMessage);
        
        $client = new ClientTcpStub();
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
    }
    
    /**
     * @covers Artax\Http\Client::readEntityBodyWithContentLength
     */
    public function testRequestStopsReadingEntityBodyAfterZeroBytesOnResponseWithZeroContentLength() {
        // Register a custom stream wrapper so we can control the responses our requests receive
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        // Build a custom HTTP response message for the stubbed stream wrapper to return
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine('HTTP/1.1 200 OK');
        $responseMsg->setAllHeaders(array(
            'Content-Length' => 0
        ));
        
        CustomStreamWrapper::setBody($responseMsg);
        
        $client = new ClientTcpStub();
        $request = new StdRequest('http://localhost', 'GET', array('Connection'=>'close'));
        $response = $client->request($request);
        $this->assertInstanceOf('Artax\\Http\\Response', $response);
        $this->assertEquals('', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\Client::writeRequestToStream
     * @covers Artax\Http\Client::streamOutboundRequestBody
     */
    public function testRequestStreamsOutboundEntityBodyIfPossible() {
        $this->markTestIncomplete();
        /*
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'CustomStreamWrapper');
        CustomStreamWrapper::reset();
        
        $responseMsg = new MutableStdResponse();
        $responseMsg->setStartLine('HTTP/1.1 200 OK');
        $responseMsg->setBody('test');
        $responseMsg->setAllHeaders(array('Content-Length' => 4));
        
        CustomStreamWrapper::setBody($responseMsg);
        
        $outboundStream = fopen('php://memory', 'r+');
        fwrite($outboundStream, 'out');
        rewind($outboundStream);
        
        $client = new ClientTcpStub();
        $request = new StdRequest('http://localhost', 'POST', array(), $outboundStream);
        $response = $client->request($request);
        
        
        $this->assertEquals('out', CustomStreamWrapper::$writtenData);
        */
    }
    
    
}









class ClientTcpStub extends Client {
    protected function getTcpStream($uri, $flags, $context = array()) {
        $stream = fopen('http://somewhere', 'r+');
        return array($stream, 0, 'no error');
    }
}

class CustomStreamWrapper {
    
    public $context;
    
    public static $position = 0;
    public static $body = '';
    public static $falseOnRead = false;
    public static $falseOnWrite = false;
    public static $writtenData = '';
    
    public static function setBody($response) {
        static::$body = (string) $response;
    }
    
    public static function reset() {
        static::$position = 0;
        static::$body = 0;
        static::$falseOnRead = false;
        static::$falseOnWrite = false;
        static::$writtenData = '';
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }

    public function stream_read($bytes) {
        if (static::$falseOnRead) {
            return false;
        } else {
            $chunk = substr(static::$body, static::$position, $bytes);
            static::$position += strlen($chunk);
            return $chunk;
        }
    }
    
    public function stream_write($data) {
        static::$writtenData .= $data;
        return static::$falseOnWrite ? false : strlen($data);
    }

    public function stream_eof() {
        return static::$position >= strlen(static::$body);
    }
    
    public function stream_tell() {
        return static::$position;
    }
    
    public function stream_close() {
        return null;
    }
}
