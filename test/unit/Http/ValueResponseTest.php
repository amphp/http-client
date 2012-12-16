<?php

use Artax\Http\ValueResponse;

class ValueResponseTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidStatusCodes() {
        return array(
            array(099),
            array('1xx'),
            array(600),
            array(42),
            array(null)
        );
    }
    
    /**
     * @dataProvider provideInvalidStatusCodes
     * @expectedException Ardent\DomainException
     */
    public function testConstructorThrowsExceptionOnInvalidStatusCode($badStatus) {
        $response = new ValueResponse('1.1', $badStatus);
    }
    
    public function provideInvalidReasonPhrases() {
        return array(
            array("has illegal \r control char"),
            array("has illegal \n control char")
        );
    }
    
    /**
     * @dataProvider provideInvalidReasonPhrases
     * @expectedException Ardent\DomainException
     */
    public function testConstructorThrowsExceptionOnInvalidReasonPhrase($badReason) {
        $response = new ValueResponse('1.1', 200, $badReason);
    }
    
    public function testThatEmptyReasonPhraseIsAllowed() {
        $response = new ValueResponse('1.1', 200, ' ');
        $this->assertEquals('', $response->getReasonPhrase());
    }
    
    /**
     * @covers Artax\Http\ValueResponse::__toString
     */
    public function testToStringBuildsRawHttpResponseMessage() {
        $headers = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        $response = new ValueResponse('1.1', 200, 'OK', $headers, 'test');
        $expected = '' .
            "HTTP/1.1 200 OK\r\n" .
            "CONTENT-TYPE: text/html\r\n" .
            "CONTENT-LENGTH: 42\r\n" .
            "\r\n" .
            "test"
        ;
        
        $this->assertEquals($expected, $response->__toString());
    }
    
    /**
     * @covers Artax\Http\ValueResponse::getStatusCode
     */
    public function testStatusCodeAccessorMethodReturnsStatusCode() {
        $response = new ValueResponse('1.1', 200);
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\ValueResponse::getReasonPhrase
     */
    public function testReasonPhraseAccessorReturnsDescription() {
        $response = new ValueResponse('1.1', 404, 'Not Found');
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }
    
    /**
     * @covers Artax\Http\ValueResponse::getStartLine
     */
    public function testStartLineGetterReturnsRawStartLineString() {
        $response = new ValueResponse('1.0', 405, 'Method Not Allowed');
        $this->assertEquals('HTTP/1.0 405 Method Not Allowed', $response->getStartLine());
    }
}
