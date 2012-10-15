<?php

use Artax\Http\StdResponse;

class StdMessageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdMessage::getHttpVersion
     */
    public function testGetHttpVersionReturnsAssignedProperty() {
        $response = new StdResponse;
        $this->assertEquals('1.1', $response->getHttpVersion());
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHttpVersion
     */
    public function testSetHttpVersionReturnsNull() {
        $response = new StdResponse();
        $this->assertEquals('1.1', $response->getHttpVersion());
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBody
     */
    public function testGetBodyReturnsStringEntityBodyIfAssigned() {
        $response = new StdResponse;
        $this->assertEquals('', $response->getBody());
        $this->assertNull($response->setBody('entity body'));
        $this->assertEquals('entity body', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBody
     */
    public function testGetBodyReturnsBufferedStreamEntityBodyContentsIfApplicable() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = new StdResponse();
        $response->setBody($body);
        $this->assertEquals('test', $response->getBody());
        
        // Retrun the body string that was cached on the first access
        $this->assertEquals('test', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBodyStream
     */
    public function testGetBodyStreamReturnsStreamResource() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = new StdResponse();
        $response->setBody($body);
        $this->assertTrue(is_resource($response->getBodyStream()));
        $this->assertEquals('test', stream_get_contents($response->getBodyStream()));
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBodyStream
     */
    public function testGetBodyStreamReturnsNullIfNoResourceAssignedToEntityBody() {
        $response = new StdResponse();
        $response->setBody('test');
        $this->assertNull($response->getBodyStream());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeader
     * @expectedException Spl\DomainException
     */
    public function testHeaderGetterThrowsExceptionOnInvalidHeaderRequest() {
        $response = new StdResponse;
        $response->getHeader('Doesnt-Exist');
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeader
     * @covers Artax\Http\StdMessage::normalizeHeaderName
     */
    public function testGetHeaderReturnsStringHeaderValueIfAssigned() {
        $response = new StdResponse;
        $this->assertNull($response->setHeader('Content-Type', 'text/html'));
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        
        $this->assertNull($response->setHeader('content-type', 'application/json'));
        $this->assertEquals('application/json', $response->getHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::hasHeader
     * @covers Artax\Http\StdMessage::normalizeHeaderName
     */
    public function testHasHeaderReturnsBoolOnHeaderExistence() {
        $response = new StdResponse;
        $this->assertFalse($response->hasHeader('Content-Type'));
        $response->setHeader('Content-Type', 'text/html');
        $this->assertTrue($response->hasHeader('Content-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::getAllHeaders
     */
    public function testGetAllHeadersReturnsHeaderStorageArray() {
        $response = new StdResponse;
        $response->setHeader('Content-Type', 'text/html');
        $response->setHeader('Content-Length', 42);
        
        $expected = array(
            'Content-Type' => 'text/html',
            'Content-Length' => 42
        );
        
        $this->assertEquals($expected, $response->getAllHeaders());
    }
    
    /**
     * @covers Artax\Http\StdMessage::setAllHeaders
     * @covers Artax\Http\StdMessage::isValidIterable
     * @expectedException Spl\TypeException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidIterable() {
        $response = new StdResponse();
        $response->setAllHeaders('not an iterable -- should throw exception');
    }
    
    public function provideInvalidRawHeaders() {
        return array(
            array('When in the chronicle of wasted time, I see descriptions of the fairest wights'),
            array('X-Responseed-By'),
            array("Content-Type: text/html\r\nContent-Length: 42"),
            array("Vary: Accept,Accept-Charset,\r\nAccept-Encoding")
        );
    }
    
    /**
     * @dataProvider provideInvalidRawHeaders
     * @covers Artax\Http\StdMessage::setRawHeader
     * @expectedException Spl\ValueException
     */
    public function testSetRawHeaderThrowsExceptionOnInvalidFormat($rawHeaderStr) {
        $response = new StdResponse;
        $response->setRawHeader($rawHeaderStr);
    }
    
    /**
     * @covers Artax\Http\StdMessage::setRawHeader
     */
    public function testSetRawHeaderParsesValidFormats() {
        $response = new StdResponse;
        
        $response->setRawHeader("Content-Type: text/html;q=0.9,\r\n\t*/*");
        $this->assertEquals('text/html;q=0.9, */*', $response->getHeader('Content-Type'));
        
        $response->setRawHeader('Content-Encoding: gzip');
        $this->assertEquals('gzip', $response->getHeader('Content-Encoding'));
        
        $response->setRawHeader("Content-Type: text/html;q=0.9,\r\n\t   application/json,\r\n */*");
        $this->assertEquals('text/html;q=0.9, application/json, */*',
            $response->getHeader('Content-Type')
        );
    }
    
    /**
     * @covers Artax\Http\StdMessage::removeAllHeaders
     */
    public function testRemoveAllHeaders() {
        $response = new StdResponse();
        $response->setHeader('Content-Type', 'text/plain');
        $headers = $response->getAllHeaders();
        $this->assertFalse(empty($headers));
        $response->removeAllHeaders();
        $this->assertEquals(array(), $response->getAllHeaders());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getRawHeaders
     */
    public function testGetRawHeadersReturnsStringHeadersInRawMessageFormat() {
        $response = new StdResponse();
        $response->setAllHeaders(array(
            'Content-Type' => 'text/plain',
            'Content-Length' => 42,
            'X-MyHeader' => 'some-val'
        ));
        
        $expected = "Content-Type: text/plain\r\n" .
                    "Content-Length: 42\r\n" .
                    "X-MyHeader: some-val\r\n";
        
        $this->assertEquals($expected, $response->getRawHeaders());
    }
    
    /**
     * @covers Artax\Http\StdMessage::isMultiHeader
     */
    public function testIsMultiHeaderReturnsBooleanOnHeaderValueCount() {
        $response = new StdResponse();
        $response->setHeader('Set-Cookie', array(1, 2, 3));
        $this->assertTrue($response->isMultiHeader('SET-COOKIE'));
        $response->setHeader('Set-Cookie', array(1));
        $this->assertFalse($response->isMultiHeader('set-cookie'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::isMultiHeader
     * @expectedException Spl\DomainException
     */
    public function testIsMultiHeaderThrowsExceptionOnOutOfBoundsHeaderName() {
        $response = new StdResponse();
        $response->isMultiHeader('X-Doesnt-Exist');
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHeader
     */
    public function testSetHeaderTrimsTrailingColonThenAssignsValueAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setHeader('Accept:', 'text/*'));
        $this->assertEquals('text/*', $response->getHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHeader
     * @covers Artax\Http\StdMessage::setAllHeaders
     */
    public function testSetAllHeadersAssignsValuesAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setAllHeaders(array('Accept'=>'text/*')));
        $this->assertEquals('text/*', $response->getHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::removeHeader
     */
    public function testRemoveHeaderClearsSpecifiedHeaderAndReturnsNull() {
        $response = new StdResponse();
        $response->setHeader('Accept', 'text/*');
        $this->assertEquals('text/*', $response->getHeader('accept'));
        $this->assertNull($response->removeHeader('Accept'));
        $this->assertFalse($response->hasHeader('Accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::appendAllHeaders
     * @covers Artax\Http\StdMessage::isValidIterable
     * @expectedException Spl\TypeException
     */
    public function testAppendAllHeadersThrowsExceptionOnInvalidIterable() {
        $response = new StdResponse();
        $response->appendAllHeaders('not an iterable -- should throw exception');
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHeader
     * @covers Artax\Http\StdMessage::appendHeader
     * @covers Artax\Http\StdMessage::appendAllHeaders
     */
    public function testAppendAllHeadersAssignsValuesAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->appendAllHeaders(array('Accept'=>'text/*')));
        $this->assertEquals('text/*', $response->getHeader('accept'));
        
        $this->assertNull($response->appendAllHeaders(array('Accept'=>'*/*')));
        $this->assertEquals('text/*,*/*', $response->getHeader('accept'));
        $this->assertEquals(array('text/*','*/*'), $response->getHeaderValueArray('accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeaderValueArray
     * @expectedException Spl\DomainException
     */
    public function testGetHeaderValueArrayThrowsExceptionOnNonexistentHeader() {
        $response = new StdResponse();
        $response->getHeaderValueArray('X-Header-Not-Set');
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeaderValueArray
     */
    public function testGetHeaderValueReturnsArrayOfHeaderValues() {
        $response = new StdResponse();
        $cookieHeaders = array('header1', 'header2');
        $response->setHeader('Set-Cookie', $cookieHeaders);
        $this->assertEquals($cookieHeaders, $response->getHeaderValueArray('Set-Cookie'));
    }
}
















