<?php

use ArtaxPlugins\ResponseContentLength;

require dirname(__DIR__) . '/src/ResponseContentLength.php';

class ResponseContentLengthTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseContentLength::__invoke
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $response = $this->getMock('Artax\\Http\\Response');
        $mock = $this->getMock(
            'ArtaxPlugins\\ResponseContentLength',
            array('setContentLengthHeader')
        );
        $mock->expects($this->once())
             ->method('setContentLengthHeader')
             ->with($response);
        $mock($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseContentLength::setContentLengthHeader
     */
    public function testSetContentLengthHeaderAssignsResponseBodyLengthToResponseHeader() {
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('getBody')
                 ->will($this->returnValue('five'));
        
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Content-Length', 4);
        
        $plugin = new ResponseContentLength;
        $plugin($response);
    }
}
