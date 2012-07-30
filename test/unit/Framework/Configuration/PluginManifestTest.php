<?php

use Artax\Framework\Configuration\PluginManifest;

class PluginManifestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\PluginManifest::__construct
     */
    public function testBeginsEmpty() {
        $manifest = new PluginManifest();
        $this->assertEquals('', $manifest->get('name'));
        $this->assertEquals('', $manifest->get('description'));
        $this->assertEquals(0, $manifest->get('version'));
        $this->assertEquals(0, $manifest->get('minSystemVersion'));
        $this->assertEquals(array(), $manifest->get('pluginDependencies'));
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginManifest::populate
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnInvalidMapIterable() {
        $manifest = new PluginManifest();
        $manifest->populate('not a valid map iterable');
    }
    
    /**
     * @covers Artax\Framework\Configuration\PluginManifest::populate
     * @covers Artax\Framework\Configuration\PluginManifest::setName
     * @covers Artax\Framework\Configuration\PluginManifest::setDescription
     * @covers Artax\Framework\Configuration\PluginManifest::setVersion
     * @covers Artax\Framework\Configuration\PluginManifest::setMinSystemVersion
     * @covers Artax\Framework\Configuration\PluginManifest::setPluginDependencies
     * @covers Artax\Framework\Configuration\PluginManifest::validate
     * @covers Artax\Framework\Configuration\PluginManifest::setRequiredFiles
     * @covers Artax\Framework\Configuration\PluginManifest::setEventListeners
     * @covers Artax\Framework\Configuration\PluginManifest::setInjectionDefinitions
     * @covers Artax\Framework\Configuration\PluginManifest::setInjectionImplementations
     * @covers Artax\Framework\Configuration\PluginManifest::setSharedClasses
     * @covers Artax\Framework\Configuration\PluginManifest::isTraversableMap
     * @covers Artax\Framework\Configuration\PluginManifest::__toString
     */
    public function testPopulateUsesSettersToAssignDirectives() {
        $manifest = new PluginManifest();
        
        $eventListeners = new StdClass;
        $eventListeners->test = 'Listener';
        
        $vals = array(
            'name' => 'TestPlugin',
            'description' => 'test description',
            'version' => 1,
            'minSystemVersion' => 2,
            'pluginDependencies' => array('SomeRequiredPlugin'),
            'requiredFiles' => array('/test'),
            'eventListeners' => $eventListeners,
            'injectionDefinitions' => array('D' => array('A'=>'N')),
            'injectionImplementations' => array('R' => array('D'=>'L')),
            'sharedClasses' => array()
        );
        $manifest->populate($vals);
        
        $this->assertEquals('TestPlugin', $manifest->__toString());
        $this->assertEquals('TestPlugin', $manifest->get('name'));
        $this->assertEquals('test description', $manifest->get('description'));
        $this->assertEquals(1, $manifest->get('version'));
        $this->assertEquals(2, $manifest->get('minSystemVersion'));
        $this->assertEquals(array('SomeRequiredPlugin'), $manifest->get('pluginDependencies'));
        $this->assertEquals(array('/test'), $manifest->get('requiredFiles'));
        $this->assertEquals(array('D'=>array('A'=>'N')), $manifest->get('injectionDefinitions'));
        $this->assertEquals(array('R'=>array('D'=>'L')), $manifest->get('injectionImplementations'));
        $this->assertEquals($eventListeners, $manifest->get('eventListeners'));
        
    }
    
    public function provideInvalidDirectiveValues() {
        return array(
            array(array()),
            array(array('name' => 42)),
            array(array('name' => 'test', 'description' => 42)),
            array(array('name' => 'test', 'version' => '42')),
            array(array('name' => 'test', 'minSystemVersion' => '42')),
            array(array('name' => 'test', 'pluginDependencies' => 42)),
            
            array(array('name' => 'test', 'requiredFiles' => 42)),
            array(array('name' => 'test', 'eventListeners' => 42)),
            array(array('name' => 'test', 'injectionDefinitions' => 42)),
            array(array('name' => 'test', 'injectionImplementations' => 42)),
            array(array('name' => 'test', 'sharedClasses' => new StdClass)),
        );
    }
    
    /**
     * @dataProvider provideInvalidDirectiveValues
     * @covers Artax\Framework\Configuration\PluginManifest::isTraversable
     * @covers Artax\Framework\Configuration\PluginManifest::isTraversableMap
     * @covers Artax\Framework\Configuration\PluginManifest::populate
     * @covers Artax\Framework\Configuration\PluginManifest::setName
     * @covers Artax\Framework\Configuration\PluginManifest::setDescription
     * @covers Artax\Framework\Configuration\PluginManifest::setVersion
     * @covers Artax\Framework\Configuration\PluginManifest::setMinSystemVersion
     * @covers Artax\Framework\Configuration\PluginManifest::setPluginDependencies
     * @covers Artax\Framework\Configuration\PluginManifest::validate
     * @covers Artax\Framework\Configuration\PluginManifest::setRequiredFiles
     * @covers Artax\Framework\Configuration\PluginManifest::setEventListeners
     * @covers Artax\Framework\Configuration\PluginManifest::setInjectionDefinitions
     * @covers Artax\Framework\Configuration\PluginManifest::setInjectionImplementations
     * @covers Artax\Framework\Configuration\PluginManifest::setSharedClasses
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnInvalidDirective($values) {
        $manifest = new PluginManifest();
        $manifest->populate($values);
    }
    
}
