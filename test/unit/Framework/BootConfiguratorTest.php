<?php

use Artax\Framework\BootConfigurator,
    Artax\Framework\Config\Config;

class BootConfiguratorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\BootConfigurator::__construct
     */
    public function testBeginsEmpty() {
        $injector = $this->getMock('Artax\\Injection\\Injector');
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        
        $configurator = new BootConfigurator($injector, $mediator);
        $this->assertInstanceOf('Artax\\Framework\\BootConfigurator', $configurator);
    }
    
    /**
     * @covers Artax\Framework\BootConfigurator::__construct
     * @covers Artax\Framework\BootConfigurator::configure
     * @covers Artax\Framework\BootConfigurator::enableRouteShortcuts
     * @covers Artax\Framework\BootConfigurator::enableAutoResponseContentLength
     * @covers Artax\Framework\BootConfigurator::enableAutoResponseDate
     * @covers Artax\Framework\BootConfigurator::enableAutoResponseStatus
     * @covers Artax\Framework\BootConfigurator::enableAutoResponseEncode
     */
    public function testConfigureEnablesCorePlugins() {
        $injector = $this->getMock('Artax\\Injection\\Injector');
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        
        $configurator = new BootConfigurator($injector, $mediator);
        
        $config = new Config();
        $config->populate(array(
            'applyRouteShortcuts' => 'yes',
            'autoResponseStatus' => 'true',
            'autoResponseDate' => 1,
            'autoResponseContentLength' => 'yes',
            'autoResponseEncode' => 'yes',
            'autoResponseEncodeMediaRanges' => array('text/html'),
            'routes' => array(
                '/' => 'Resources\\Index'
            ),
            'eventListeners' => array(),
            'injectionDefinitions' => array(),
            'injectionImplementations' => array(),
            'sharedClasses' => array('TestClass')
        ));
        
        $configurator->configure($config);
    }
    
}
