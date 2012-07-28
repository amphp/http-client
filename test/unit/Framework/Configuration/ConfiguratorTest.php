<?php

use Artax\Framework\Configuration\Configurator,
    Artax\Framework\Configuration\Config,
    org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

org\bovigo\vfs\vfsStreamWrapper::register();

vfsStream::copyFromFileSystem(
    ARTAX_SYSTEM_DIR . '/test/fixture/vfs/config', vfsStream::setup('root')
);

class ConfiguratorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\Configurator::__construct
     */
    public function testBeginsEmpty() {
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $configurator = new Configurator($injector, $mediator);
        $this->assertInstanceOf('Artax\Framework\Configuration\Configurator', $configurator);
    }
    
    /**
     * @covers Artax\Framework\Configuration\Configurator::__construct
     * @covers Artax\Framework\Configuration\Configurator::configure
     * @covers Artax\Framework\Configuration\Configurator::requireFile
     */
    public function testConfigureAppliesConfigurationDirectives() {
        $directives = array(
            'requiredFiles' => array('vfs://root/config-require.php'),
            'routes' => array(
                '/' => 'Resources\Index'
            ),
            'eventListeners' => array(),
            'injectionDefinitions' => array(),
            'injectionImplementations' => array(),
            'sharedClasses' => array('TestClass')
        );
        
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $injector->expects($this->once())
                 ->method('defineAll')
                 ->with($directives['injectionDefinitions']);
        $injector->expects($this->once())
                 ->method('implementAll')
                 ->with($directives['injectionImplementations']);
        $injector->expects($this->once())
                 ->method('shareAll')
                 ->with($directives['sharedClasses']);
        
        $mediator = $this->getMock('Artax\Events\Mediator');
        $mediator->expects($this->once())
                 ->method('pushAll')
                 ->with($directives['eventListeners']);
        
        $configurator = new Configurator($injector, $mediator);
        
        $config = new Config();
        $config->populate($directives);
        $configurator->configure($config);
    }
    /**
     * @covers Artax\Framework\Configuration\Configurator::configure
     * @covers Artax\Framework\Configuration\Configurator::requireFile
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testConfigureThrowsExceptionOnRequiredFileFailure() {
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $configurator = new Configurator($injector, $mediator);
        
        $config = new Config();
        $config->populate(array('requiredFiles' => array('vfs://root/nonexistentFile.php')));
        $configurator->configure($config);
    }
    
}
