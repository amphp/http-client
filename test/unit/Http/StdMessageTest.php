<?php

use Artax\Http\StdResponse;

class StdMessageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdMessage::setProtocol
     * @covers Artax\Http\StdMessage::getProtocol
     */
    public function testSetProtocolReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->getProtocol());
        $this->assertNull($response->setProtocol('1.0'));
        $this->assertEquals('1.0', $response->getProtocol());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBody
     */
    public function testGetBodyReturnsEntityBodyIfAssigned() {
        $response = new StdResponse;
        $this->assertEquals('', $response->getBody());
        $this->assertNull($response->setBody('entity body'));
        $this->assertEquals('entity body', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getCombinedHeader
     */
    public function testGetFoldedHeaderReturnsStringHeaderValueIfAssigned() {
        $response = new StdResponse;
        $this->assertNull($response->setHeader('Content-Type', 'text/html'));
        $this->assertEquals('text/html', $response->getCombinedHeader('Content-Type'));
        
        $this->assertNull($response->setHeader('content-type', 'application/json'));
        $this->assertEquals('application/json', $response->getCombinedHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::hasHeader
     */
    public function testHasHeaderReturnsBoolOnHeaderExistence() {
        $response = new StdResponse;
        $this->assertFalse($response->hasHeader('Content-Type'));
        $response->setHeader('Content-Type', 'text/html');
        $this->assertTrue($response->hasHeader('Content-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::setAllHeaders
     * @expectedException Ardent\TypeException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidIterable() {
        $response = new StdResponse();
        $response->setAllHeaders('not an iterable -- should throw exception');
    }
    
    public function provideInvalidRawHeaders() {
        return array(
            array('When in the chronicle of wasted time, I see descriptions of the fairest wights'),
            array('X-Some-Header-Without-Colon'),
            array("Content-Type: text/html\r\nContent-Length: 42"),
            array("Vary: Accept,Accept-Charset,\r\nAccept-Encoding")
        );
    }
    
    /**
     * @covers Artax\Http\StdMessage::removeAllHeaders
     */
    public function testRemoveAllHeaders() {
        $response = new StdResponse();
        $this->assertFalse($response->hasHeader('Content-Type'));
        $response->setHeader('Content-Type', 'text/plain');
        $this->assertTrue($response->hasHeader('Content-Type'));
        $response->removeAllHeaders();
        $this->assertFalse($response->hasHeader('Content-Type'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHeader
     */
    public function testSetHeaderTrimsTrailingColonThenAssignsValueAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setHeader('Accept:', 'text/*'));
        $this->assertEquals('text/*', $response->getCombinedHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHeader
     * @covers Artax\Http\StdMessage::setAllHeaders
     */
    public function testSetAllHeadersAssignsValuesAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setAllHeaders(array('Accept'=>'text/*')));
        $this->assertEquals('text/*', $response->getCombinedHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::removeHeader
     */
    public function testRemoveHeaderClearsSpecifiedHeaderAndReturnsNull() {
        $response = new StdResponse();
        $response->setHeader('Accept', 'text/*');
        $this->assertEquals('text/*', $response->getCombinedHeader('accept'));
        $this->assertNull($response->removeHeader('Accept'));
        $this->assertFalse($response->hasHeader('Accept'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::addAllHeaders
     * @expectedException Ardent\TypeException
     */
    public function testAppendAllHeadersThrowsExceptionOnInvalidIterable() {
        $response = new StdResponse();
        $response->addAllHeaders('not an iterable -- should throw exception');
    }
    
    /**
     * @covers Artax\Http\StdMessage::setHeader
     * @covers Artax\Http\StdMessage::addHeader
     * @covers Artax\Http\StdMessage::addAllHeaders
     */
    public function testAppendAllHeadersAssignsValuesAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->addAllHeaders(array('Accept'=>'text/*')));
        $this->assertEquals('text/*', $response->getCombinedHeader('accept'));
        
        $this->assertNull($response->addAllHeaders(array('Accept'=>'*/*')));
        $this->assertEquals('text/*,*/*', $response->getCombinedHeader('accept'));
    }
}
















