<?php

use ArtaxPlugins\ResponseContentLength\ContentLengthApplier;

class ContentLengthApplierTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseContentLength\ContentLengthApplier::__invoke
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $response = $this->getMock('Artax\\Http\\Response');
        $mock = $this->getMock(
            'ArtaxPlugins\\ResponseContentLength\\ContentLengthApplier',
            array('setContentLengthHeader')
        );
        $mock->expects($this->once())
             ->method('setContentLengthHeader')
             ->with($response);
        $mock($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseContentLength\ContentLengthApplier::setContentLengthHeader
     */
    public function testSetContentLengthHeaderAssignsResponseBodyLengthToResponseHeader() {
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('getBody')
                 ->will($this->returnValue('five'));
        
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Content-Length', 4);
        
        $plugin = new ContentLengthApplier;
        $plugin($response);
    }
}
