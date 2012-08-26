<?php

use Artax\Http\StdResponse,
    Artax\Http\StatusCodes,
    ArtaxPlugins\ResponseStatus;

require dirname(__DIR__) . '/src/ResponseStatus.php';

class ResponseStatusTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseStatus::__invoke
     */
    public function testMagicInvokeCallsWorkMethods() {
        $response = $this->getMock('Artax\\Http\\Response');
        
        $pluginMock = $this->getMock(
            'ArtaxPlugins\ResponseStatus',
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
     * @covers ArtaxPlugins\ResponseStatus::setStatusCode
     */
    public function testSetStatusCodeAssigns200CodeIfNoneHasYetBeenAssigned() {
        $response = new StdResponse;
        $plugin = new ResponseStatus;
        
        $response->setStatusCode(0);
        
        $this->assertEquals(0, $response->getStatusCode());
        $this->assertNull($plugin->setStatusCode($response));
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    /**
     * @covers ArtaxPlugins\ResponseStatus::setStatusDescription
     */
    public function testSetStatusDescriptionAssignsConstantDefaultIfAvailable() {
        $response = new StdResponse;
        $plugin = new ResponseStatus;
        
        $response->setStatusCode(404);
        $this->assertNull($response->getStatusDescription());
        $this->assertNull($plugin->setStatusDescription($response));
        $this->assertEquals(StatusCodes::HTTP_404, $response->getStatusDescription());
    }
}
