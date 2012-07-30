<?php

use Artax\Framework\Configuration\AppConfig;
        
class AppConfigTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::__construct
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @covers Artax\Framework\Configuration\AppConfig::setRoutes
     * @covers Artax\Framework\Configuration\AppConfig::setPlugins
     */
    public function testPopulateReturnsNullAndAssignsSpecifiedValues() {
        $config = new AppConfig();
        
        $cfgVals = array(
            'routes'  => array('/' => 'MyNamespace\HomeResource'),
            'plugins' => array('RouteShortcuts' => true)
        );
        $this->assertEquals(array(), $config->get('routes'));
        $this->assertEquals(array(), $config->get('plugins'));
        $this->assertNull($config->populate($cfgVals));
        $this->assertEquals($cfgVals['routes'], $config->get('routes'));
        $this->assertEquals($cfgVals['plugins'], $config->get('plugins'));
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnInvalidMapIterable() {
        $config = new AppConfig();
        $config->populate('not a map iterable');
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::get
     * @expectedException DomainException
     */
    public function testGetThrowsExceptionOnInvalidDirectiveName() {
        $cfg = new AppConfig();
        $cfg->get('doesntExist');
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::get
     */
    public function testGetReturnsDirectiveValue() {
        $cfg = new AppConfig();
        $cfg->populate(array('testDirective' => true));
        $this->assertTrue(is_bool($cfg->get('testDirective')));
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::has
     */
    public function testHasReturnsBooleanExistenceStatusOfSpecifiedDirective() {
        $cfg = new AppConfig();
        $this->assertFalse($cfg->has('applyRouteShortcuts'));
        $cfg->populate(array('testDirective' => true));
        $this->assertTrue($cfg->has('testDirective'));
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @covers Artax\Framework\Configuration\AppConfig::isTraversableMap
     * @covers Artax\Framework\Configuration\AppConfig::assignCustomDirective
     * @covers Artax\Framework\Configuration\AppConfig::setRoutes
     * @covers Artax\Framework\Configuration\AppConfig::setPlugins
     */
    public function testPopulateCallsDirectiveSetterMethodsIfDefined() {
        $cfg = new AppConfigTestWithSetter();
        $return = $cfg->populate(array(
            'routes' => array(
                '/' => 'Index'
            ),
            'plugins' => array(
                'ResponseStatus' => true
            ),
            'customDirective' => 42
        ));
        
        $this->assertNull($return);
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @covers Artax\Framework\Configuration\AppConfig::assignCustomDirective
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnNonScalarCustomValue() {
        $cfg = new AppConfigTestWithSetter();
        $cfg->populate(array(
            'someDirectiveThatIsNotScalar' => array()
        ));
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @covers Artax\Framework\Configuration\AppConfig::setRoutes
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnInvalidRoutes() {
        $cfg = new AppConfigTestWithSetter();
        $return = $cfg->populate(array(
            'routes' => 'not an iterable map'
        ));
        
        $this->assertNull($return);
    }
    
    /**
     * @covers Artax\Framework\Configuration\AppConfig::populate
     * @covers Artax\Framework\Configuration\AppConfig::setPlugins
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testPopulateThrowsExceptionOnInvalidPlugins() {
        $cfg = new AppConfigTestWithSetter();
        $return = $cfg->populate(array(
            'plugins' => 'not an iterable map'
        ));
        
        $this->assertNull($return);
    }
    
}

class AppConfigTestWithSetter extends AppConfig {
    protected function setCustomUserDirective($val) {
        $this->directives['customUserDirective'] = $val;
    }
}
