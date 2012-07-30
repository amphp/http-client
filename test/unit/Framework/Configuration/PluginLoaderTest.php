<?php

use Artax\Framework\Configuration\PluginLoader,
    Artax\Framework\Configuration\Configurator,
    Artax\Framework\Configuration\PluginManifestFactory;

class PluginLoaderTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\PluginLoader::__construct
     */
    public function testBeginsEmpty() {
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        $configurator = new Configurator($injector, $mediator);
        $configParser = $this->getMock('Artax\Framework\Configuration\Parsers\ConfigParser');
        $pluginManifestFactory = new PluginManifestFactory;
        $pluginDirectory = '';
        $currentSystemVersion = 0;
        
        $loader = new PluginLoader(
            $configurator,
            $configParser,
            $pluginManifestFactory,
            $pluginDirectory,
            $currentSystemVersion
        );
        
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginLoader::load
     */
    public function testLoadSkipsDisabledPlugins() {
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        $configurator = new Configurator($injector, $mediator);
        $configParser = $this->getMock('Artax\Framework\Configuration\Parsers\ConfigParser');
        $pluginManifestFactory = new PluginManifestFactory;
        $pluginDirectory = '';
        $currentSystemVersion = 0;
        
        $loader = new PluginLoader(
            $configurator,
            $configParser,
            $pluginManifestFactory,
            $pluginDirectory,
            $currentSystemVersion
        );
        
        $this->assertEquals(array(), $loader->load(array('Plugin' => false)));
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginLoader::load
     * @covers Artax\Framework\Configuration\PluginLoader::applyPlugin
     * @covers Artax\Framework\Configuration\PluginLoader::validateSystemVersion
     * @covers Artax\Framework\Configuration\PluginLoader::validateDependencies
     */
    public function testLoadAppliesManifestConfigurationsAndReturnsArrayOfLoadedManifests() {
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $parsedCfgVals = new StdClass;
        $parsedCfgVals->name = 'TestPlugin';
        $parsedCfgVals->version = 0.1;
        $parsedCfgVals->minSystemVersion = 0;
        $parsedCfgVals->pluginDependencies = array();

        $configParser = $this->getMock('Artax\Framework\Configuration\Parsers\ConfigParser');
        $configParser->expects($this->once())
                     ->method('parse')
                     ->will($this->returnValue($parsedCfgVals));
        
        $pluginManifestFactory = new PluginManifestFactory;
        $pluginDirectory = '/test/dir';
        $currentSystemVersion = 0;
        
        
        $manifest = $pluginManifestFactory->make($parsedCfgVals);
        
        $configurator = $this->getMock(
            'Artax\Framework\Configuration\Configurator',
            null,
            array($injector, $mediator)
        );
        
        $loader = new PluginLoader(
            $configurator,
            $configParser,
            $pluginManifestFactory,
            $pluginDirectory,
            $currentSystemVersion
        );
        
        $this->assertEquals(array($manifest), $loader->load(array('TestPlugin' => true)));
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginLoader::load
     * @covers Artax\Framework\Configuration\PluginLoader::validateSystemVersion
     * @expectedException Artax\Framework\Configuration\PluginException
     */
    public function testLoadFailsIfMinSystemVersionNotMet() {
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $parsedCfgVals = new StdClass;
        $parsedCfgVals->name = 'TestPlugin';
        $parsedCfgVals->version = 0.1;
        $parsedCfgVals->minSystemVersion = 999;
        $parsedCfgVals->pluginDependencies = array();

        $configParser = $this->getMock('Artax\Framework\Configuration\Parsers\ConfigParser');
        $configParser->expects($this->once())
                     ->method('parse')
                     ->will($this->returnValue($parsedCfgVals));
        
        $pluginManifestFactory = new PluginManifestFactory;
        $pluginDirectory = '/test/dir';
        $currentSystemVersion = 0;
        
        $configurator = $this->getMock(
            'Artax\Framework\Configuration\Configurator',
            null,
            array($injector, $mediator)
        );
        
        $loader = new PluginLoader(
            $configurator,
            $configParser,
            $pluginManifestFactory,
            $pluginDirectory,
            $currentSystemVersion
        );
        
        $loader->load(array('TestPlugin' => true));
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginLoader::load
     * @covers Artax\Framework\Configuration\PluginLoader::validateSystemVersion
     * @covers Artax\Framework\Configuration\PluginLoader::validateDependencies
     * @expectedException Artax\Framework\Configuration\PluginException
     */
    public function testLoadFailsIfDependencyRequirementNotMet() {
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $parsedCfgVals = new StdClass;
        $parsedCfgVals->name = 'TestPlugin';
        $parsedCfgVals->version = 0.1;
        $parsedCfgVals->minSystemVersion = 0;
        $parsedCfgVals->pluginDependencies = array('UnloadedDependency');

        $configParser = $this->getMock('Artax\Framework\Configuration\Parsers\ConfigParser');
        $configParser->expects($this->once())
                     ->method('parse')
                     ->will($this->returnValue($parsedCfgVals));
        
        $pluginManifestFactory = new PluginManifestFactory;
        $pluginDirectory = '/test/dir';
        $currentSystemVersion = 0;
        
        $configurator = $this->getMock(
            'Artax\Framework\Configuration\Configurator',
            null,
            array($injector, $mediator)
        );
        
        $loader = new PluginLoader(
            $configurator,
            $configParser,
            $pluginManifestFactory,
            $pluginDirectory,
            $currentSystemVersion
        );
        
        $loader->load(array('TestPlugin' => true));
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginLoader::load
     * @expectedException Artax\Framework\Configuration\PluginException
     */
    public function testLoadFailsIfConfigProcessingThrowsException() {
        
        $injector = $this->getMock('Artax\Injection\Injector');
        $mediator = $this->getMock('Artax\Events\Mediator');
        
        $parsedCfgVals = new StdClass;
        $parsedCfgVals->name = 'TestPlugin';
        $parsedCfgVals->version = 0.1;
        $parsedCfgVals->minSystemVersion = 0;
        $parsedCfgVals->pluginDependencies = array('UnloadedDependency');

        $configParser = $this->getMock('Artax\Framework\Configuration\Parsers\ConfigParser');
        $configParser->expects($this->once())
                     ->method('parse')
                     ->will($this->throwException(new Exception));
        
        $pluginManifestFactory = new PluginManifestFactory;
        $pluginDirectory = '/test/dir';
        $currentSystemVersion = 0;
        
        $configurator = $this->getMock(
            'Artax\Framework\Configuration\Configurator',
            null,
            array($injector, $mediator)
        );
        
        $loader = new PluginLoader(
            $configurator,
            $configParser,
            $pluginManifestFactory,
            $pluginDirectory,
            $currentSystemVersion
        );
        
        $loader->load(array('TestPlugin' => true));
    }
    
}
