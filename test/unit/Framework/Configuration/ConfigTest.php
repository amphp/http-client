<?php

use Artax\Framework\Configuration\Config;
        
class ConfigTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\Config::get
     * @expectedException DomainException
     */
    public function testGetThrowsExceptionOnInvalidDirectiveName() {
        $cfg = new Config();
        $cfg->get('doesntExist');
    }
    
    /**
     * @covers Artax\Framework\Configuration\Config::get
     */
    public function testGetReturnsDirectiveValue() {
        $cfg = new Config();
        $cfg->populate(array('testDirective' => true));
        $this->assertTrue(is_bool($cfg->get('testDirective')));
    }
    
    /**
     * @covers Artax\Framework\Configuration\Config::has
     */
    public function testHasReturnsBooleanExistenceStatusOfSpecifiedDirective() {
        $cfg = new Config();
        $this->assertFalse($cfg->has('applyRouteShortcuts'));
        $cfg->populate(array('testDirective' => true));
        $this->assertTrue($cfg->has('testDirective'));
    }
    
    /**
     * @covers Artax\Framework\Configuration\Config::populate
     */
    public function testPopulateCallsDirectiveSetterMethodsIfDefined() {
        $cfg = new ConfigTestWithSetter();
        $return = $cfg->populate(array(
            'applyRouteShortcuts' => 'yes',
            'autoResponseStatus' => 'true',
            'autoResponseDate' => 1,
            'autoResponseContentLength' => 'yes',
            'autoResponseEncode' => 'no',
            'autoResponseEncodeMediaRanges' => array('text/html'),
            'customUserDirective' => 42
        ));
        
        $this->assertNull($return);
    }
    
    /**
     * @covers Artax\Framework\Configuration\Config::populate
     * @expectedException InvalidArgumentException
     */
    public function testPopulateThrowsExceptionOnInvalidIterableParameter() {
        $cfg = new Config();
        $cfg->populate('not iterable');
    }
}

class ConfigTestWithSetter extends Config {
    protected function setCustomUserDirective($val) {
        $this->directives['customUserDirective'] = $val;
    }
}
