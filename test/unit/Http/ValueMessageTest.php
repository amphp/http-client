<?php

use Artax\Http\ValueRequest,
    Artax\Http\StdRequest;

/**
 * @covers Artax\Http\ValueMessage
 */
class ValueMessageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @expectedException Ardent\TypeException
     */
    public function testBodyAssignmentThrowsExceptionOnInvalidType() {
        $body = new StdClass;
        $request = new ValueRequest('GET', 'http://localhost/', 1.1, array(), $body);
    }
    
    /**
     * @expectedException Ardent\KeyException
     */
    public function testGetHeadersThrowsExceptionOnNonexistentHeader() {
        $request = new ValueRequest('GET', 'http://localhost/', 1.1);
        $request->getHeaders('Doesnt-Exist');
    }
    
    public function testAppendHeaderAddsMultiplesOnArrayValue() {
        $headers = array(
            'Accept-Encoding' => array('gzip', 'deflate', 'identity')
        );
        
        $request = new ValueRequest('GET', 'http://localhost/', 1.1, $headers);
        $this->assertEquals('gzip,deflate,identity', $request->getCombinedHeader('Accept-Encoding'));
    }
    
    /**
     * @expectedException Ardent\TypeException
     */
    public function testAppendAllHeadersThrowsExceptionOnInvalidIterable() {
        $request = new ValueRequest('GET', 'http://localhost/', 1.1, 'not iterable header list');
    }
    
    public function testClearHeader() {
        $request = new StdRequest();
        $request->addAllHeaders(array(
            'Accept' => '*/*',
            'Accept-Encoding' => array('gzip', 'deflate', 'identity')
        ));
        
        $this->assertTrue($request->hasHeader('Accept'));
        $request->removeHeader('Accept');
        $this->assertFalse($request->hasHeader('Accept'));
    }
}