<?php

use Artax\Http\StdResponse,
    Artax\Http\StatusCodes,
    ArtaxPlugins\ResponseStatus\StatusApplier;

class StatusApplierTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseStatus\StatusApplier::__invoke
     */
    public function testMagicInvokeCallsWorkMethods() {
        $response = $this->getMock('Artax\\Http\\Response');
        
        $pluginMock = $this->getMock(
            'ArtaxPlugins\ResponseStatus\StatusApplier',
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
     * @covers ArtaxPlugins\ResponseStatus\StatusApplier::setStatusCode
     */
    public function testSetStatusCodeAssigns200CodeIfNoneHasYetBeenAssigned() {
        $response = new StdResponse;
        $plugin = new StatusApplier;
        
        $this->assertNull($response->getStatusCode());
        $this->assertNull($plugin->setStatusCode($response));
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * @covers ArtaxPlugins\ResponseStatus\StatusApplier::setStatusDescription
     */
    public function testSetStatusDescriptionAssignsConstantDefaultIfAvailable() {
        $response = new StdResponse;
        $plugin = new StatusApplier;
        
        $response->setStatusCode(404);
        $this->assertNull($response->getStatusDescription());
        $this->assertNull($plugin->setStatusDescription($response));
        $this->assertEquals(StatusCodes::HTTP_404, $response->getStatusDescription());
    }
}
