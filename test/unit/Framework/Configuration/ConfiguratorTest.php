<?php

use Artax\Framework\Configuration\Configurator,
    Artax\Framework\Configuration\AppConfig,
    Artax\Injection\Provider,
    Artax\Injection\ReflectionPool,
    org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

org\bovigo\vfs\vfsStreamWrapper::register();

vfsStream::copyFromFileSystem(
    ARTAX_SYSTEM_DIR . '/test/fixture/vfs/config', vfsStream::setup('root')
);

class AppConfiguratorTest extends PHPUnit_Framework_TestCase {
    
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
     * @covers Artax\Framework\Configuration\Configurator::apply
     * @covers Artax\Framework\Configuration\Configurator::requireFile
     */
    public function testConfigureAppliesConfigurationDirectives() {
        $directives = array(
            'requiredFiles' => array(
                'vfs://root/config-require.php'
            ),
            'routes' => array(
                '/' => 'Resources\Index'
            ),
            'eventListeners' => array(
                'testEvent' => 'ConfiguratorTestInterfaceImpl'
            ),
            'injectionDefinitions' => array(
                'ConfiguratorTestDefinition' => array(':stdClass' => 'StdClass')
            ),
            'injectionImplementations' => array(
                'ConfiguratorTestInterface' => 'ConfiguratorTestInterfaceImpl'
            ),
            'sharedClasses' => array(
                'TestClass'
            )
        );
        
        
        $injector = new Provider(new ReflectionPool);
        
        $mediator = $this->getMock('Artax\Events\Mediator');
        $mediator->expects($this->once())
                 ->method('pushAll')
                 ->with($directives['eventListeners']);
        
        $configurator = new Configurator($injector, $mediator);
        
        $config = new AppConfig();
        $config->populate($directives);
        $configurator->apply($config);
    }
    /**
     * @covers Artax\Framework\Configuration\Configurator::apply
     * @covers Artax\Framework\Configuration\Configurator::requireFile
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testConfigureThrowsExceptionOnRequiredFileFailure() {
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $configurator = new Configurator($injector, $mediator);
        
        $config = new AppConfig();
        $config->populate(array('requiredFiles' => array('vfs://root/nonexistentFile.php')));
        $configurator->apply($config);
    }
    
}

interface ConfiguratorTestInterface {}
class ConfiguratorTestInterfaceImpl implements ConfiguratorTestInterface {}
class ConfiguratorTestDefinition {
    public function __construct($stdClass){}
}
