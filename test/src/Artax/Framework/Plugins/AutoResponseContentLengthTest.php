<?php

use Artax\Framework\Plugins\AutoResponseContentLength;

class AutoResponseContentLengthTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseContentLength::__invoke
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $response = $this->getMock('Artax\\Http\\Response');
        $mock = $this->getMock(
            'Artax\\Framework\\Plugins\\AutoResponseContentLength',
            array('setContentLengthHeader')
        );
        $mock->expects($this->once())
             ->method('setContentLengthHeader')
             ->with($response);
        $mock($response);
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseContentLength::setContentLengthHeader
     */
    public function testSetContentLengthHeaderAssignsResponseBodyLengthToResponseHeader() {
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('getBody')
                 ->will($this->returnValue('five'));
        
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Content-Length', 4);
        
        $plugin = new AutoResponseContentLength;
        $plugin($response);
    }
}
