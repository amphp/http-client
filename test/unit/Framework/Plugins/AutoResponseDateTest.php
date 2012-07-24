<?php

use Artax\Framework\Plugins\AutoResponseDate;

class AutoResponseDateTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseDate::__invoke
     * @covers Artax\Framework\Plugins\AutoResponseDate::buildRfc1123Date
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $response = $this->getMock('Artax\\Http\\Response');
        $pluginMock = $this->getMock(
            'Artax\\Framework\\Plugins\\AutoResponseDate',
            array('setDateHeader')
        );
        $pluginMock->expects($this->once())
             ->method('setDateHeader')
             ->with($response);
        $pluginMock($response);
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseDate::__invoke
     * @covers Artax\Framework\Plugins\AutoResponseDate::buildRfc1123Date
     */
    public function testUnmockedPluginIntegration() {
        $response = $this->getMock('Artax\\Http\\Response');
        $plugin = new AutoResponseDate;
        $plugin($response);
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseDate::setDateHeader
     */
    public function testSetDateHeaderAssignsResponseBodyLengthToResponseHeader() {
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Date', 'test');
        
        $pluginMock = $this->getMock('Artax\\Framework\\Plugins\\AutoResponseDate',
            array('buildRfc1123Date')
        );
        $pluginMock->expects($this->once())
                   ->method('buildRfc1123Date')
                   ->will($this->returnValue('test'));
        
        $this->assertEquals(null, $pluginMock->setDateHeader($response));
    }
}
