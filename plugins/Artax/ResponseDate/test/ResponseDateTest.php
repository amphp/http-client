<?php

use ArtaxPlugins\ResponseDate;

require dirname(__DIR__) . '/src/ResponseDate.php';

class AutoResponseDateTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseDate::__invoke
     * @covers ArtaxPlugins\ResponseDate::buildRfc1123Date
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $response = $this->getMock('Artax\\Http\\Response');
        $pluginMock = $this->getMock(
            'ArtaxPlugins\\ResponseDate',
            array('setDateHeader')
        );
        $pluginMock->expects($this->once())
             ->method('setDateHeader')
             ->with($response);
        $pluginMock($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseDate::__invoke
     * @covers ArtaxPlugins\ResponseDate::buildRfc1123Date
     */
    public function testUnmockedPluginIntegration() {
        $response = $this->getMock('Artax\\Http\\Response');
        $plugin = new ResponseDate;
        $plugin($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseDate::setDateHeader
     */
    public function testSetDateHeaderAssignsResponseBodyLengthToResponseHeader() {
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Date', 'test');
        
        $pluginMock = $this->getMock('ArtaxPlugins\\ResponseDate',
            array('buildRfc1123Date')
        );
        $pluginMock->expects($this->once())
                   ->method('buildRfc1123Date')
                   ->will($this->returnValue('test'));
        
        $this->assertEquals(null, $pluginMock->setDateHeader($response));
    }
}
