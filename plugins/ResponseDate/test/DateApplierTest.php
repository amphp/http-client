<?php

use ArtaxPlugins\ResponseDate\DateApplier;

class AutoResponseDateTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseDate\DateApplier::__invoke
     * @covers ArtaxPlugins\ResponseDate\DateApplier::buildRfc1123Date
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $response = $this->getMock('Artax\\Http\\Response');
        $pluginMock = $this->getMock(
            'ArtaxPlugins\\ResponseDate\\DateApplier',
            array('setDateHeader')
        );
        $pluginMock->expects($this->once())
             ->method('setDateHeader')
             ->with($response);
        $pluginMock($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseDate\DateApplier::__invoke
     * @covers ArtaxPlugins\ResponseDate\DateApplier::buildRfc1123Date
     */
    public function testUnmockedPluginIntegration() {
        $response = $this->getMock('Artax\\Http\\Response');
        $plugin = new DateApplier;
        $plugin($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseDate\DateApplier::setDateHeader
     */
    public function testSetDateHeaderAssignsResponseBodyLengthToResponseHeader() {
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Date', 'test');
        
        $pluginMock = $this->getMock('ArtaxPlugins\\ResponseDate\\DateApplier',
            array('buildRfc1123Date')
        );
        $pluginMock->expects($this->once())
                   ->method('buildRfc1123Date')
                   ->will($this->returnValue('test'));
        
        $this->assertEquals(null, $pluginMock->setDateHeader($response));
    }
}
