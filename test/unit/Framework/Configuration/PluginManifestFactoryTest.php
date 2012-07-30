<?php

use Artax\Framework\Configuration\PluginManifestFactory;

class PluginManifestFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\PluginManifestFactory::make
     */
    public function testMakeReturnsNewManifest() {
        $factory = new PluginManifestFactory();
        $vals = array(
            'name' => 'TestPlugin',
            'description' => 'test description',
            'version' => 1,
            'minSystemVersion' => 2,
            'pluginDependencies' => array('SomeRequiredPlugin'),
            'requiredFiles' => array('/test'),
            'injectionDefinitions' => array('D' => array('A'=>'N')),
            'injectionImplementations' => array('R' => array('D'=>'L')),
            'sharedClasses' => array()
        );
        
        $this->assertInstanceOf(
            'Artax\Framework\Configuration\PluginManifest',
            $factory->make($vals)
        );
    }
}
