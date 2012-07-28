<?php

use Artax\Framework\Configuration\AppConfig;
        
class AppConfigTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnMissingRoutes() {
        $config = new AppConfig();
        $config->populate(array());
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @covers Artax\Framework\Configuration\AppConfig::setRoutes
     * @covers Artax\Framework\Configuration\AppConfig::setPlugins
     */
    public function testPopulateReturnsNull() {
        $config = new AppConfig();
        
        $cfgVals = array(
            'routes'  => array('/' => 'MyNamespace\HomeResource'),
            'plugins' => array('RouteShortcuts' => true)
        );
        
        $this->assertNull($config->populate($cfgVals));
    }
    
}
