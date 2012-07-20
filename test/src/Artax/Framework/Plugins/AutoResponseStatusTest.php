<?php

use Artax\Http\StdResponse,
    Artax\Http\StatusCodes,
    Artax\Framework\Plugins\AutoResponseStatus;

class AutoResponseStatusTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseStatus::__invoke
     */
    public function testMagicInvokeCallsWorkMethods() {
        $response = $this->getMock('Artax\\Http\\Response');
        
        $pluginMock = $this->getMock(
            'Artax\\Framework\\Plugins\\AutoResponseStatus',
            array('setStatusCode', 'setStatusDescription')
        );
        
        $pluginMock->expects($this->once())
             ->method('setStatusCode')
             ->with($response);
        
        $response->expects($this->any())
             ->method('getStatusCode')
             ->will($this->returnValue(200));
        
        $pluginMock->expects($this->once())
             ->method('setStatusDescription')
             ->with($response);
        
        $pluginMock($response);
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseStatus::setStatusCode
     */
    public function testSetStatusCodeAssigns200CodeIfNoneHasYetBeenAssigned() {
        $response = new StdResponse;
        $plugin = new AutoResponseStatus;
        
        $this->assertNull($response->getStatusCode());
        $this->assertNull($plugin->setStatusCode($response));
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseStatus::setStatusDescription
     */
    public function testSetStatusDescriptionAssignsConstantDefaultIfAvailable() {
        $response = new StdResponse;
        $plugin = new AutoResponseStatus;
        
        $response->setStatusCode(404);
        $this->assertNull($response->getStatusDescription());
        $this->assertNull($plugin->setStatusDescription($response));
        $this->assertEquals(StatusCodes::HTTP_404, $response->getStatusDescription());
    }
}
