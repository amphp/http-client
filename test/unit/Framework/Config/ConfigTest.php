<?php

use Artax\Framework\Config\Config;
        
class ConfigTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Config\Config::__construct
     */
    public function testBeginsEmpty() {
        $cfg = new Config();
    }
    
    /**
     * @covers Artax\Framework\Config\Config::get
     * @expectedException DomainException
     */
    public function testGetThrowsExceptionOnInvalidDirectiveName() {
        $cfg = new Config();
        $cfg->get('doesntExist');
    }
    
    /**
     * @covers Artax\Framework\Config\Config::get
     * @covers Artax\Framework\Config\Config::setUndefinedDefaults
     */
    public function testGetReturnsDirectiveValue() {
        $validator = $this->getMock('Artax\\Framework\\Config\\ConfigValidator');
        $cfg = new Config($validator);
        $cfg->populate(array());
        $this->assertTrue(is_bool($cfg->get('applyRouteShortcuts')));
    }
    
    /**
     * @covers Artax\Framework\Config\Config::has
     * @covers Artax\Framework\Config\Config::setUndefinedDefaults
     */
    public function testHasReturnsBooleanExistenceStatusOfSpecifiedDirective() {
        $validator = $this->getMock('Artax\\Framework\\Config\\ConfigValidator');
        $cfg = new Config($validator);
        $this->assertFalse($cfg->has('applyRouteShortcuts'));
        $cfg->populate(array());
        $this->assertTrue($cfg->has('applyRouteShortcuts'));
    }
    
    /**
     * @covers Artax\Framework\Config\Config::populate
     * @covers Artax\Framework\Config\Config::setApplyRouteShortcuts
     * @covers Artax\Framework\Config\Config::setAutoResponseStatus
     * @covers Artax\Framework\Config\Config::setAutoResponseDate
     * @covers Artax\Framework\Config\Config::setAutoResponseContentLength
     * @covers Artax\Framework\Config\Config::setAutoResponseEncode
     * @covers Artax\Framework\Config\Config::setAutoResponseEncodeMediaRanges
     */
    public function testPopulateCallsDirectiveSetterMethodsIfDefined() {
        $validator = $this->getMock('Artax\\Framework\\Config\\ConfigValidator');
        $validator->expects($this->once())
                  ->method('validate');
                  
        $cfg = new Config($validator);
        $cfg->populate(array(
            'applyRouteShortcuts' => 'yes',
            'autoResponseStatus' => 'true',
            'autoResponseDate' => 1,
            'autoResponseContentLength' => 'yes',
            'autoResponseEncode' => 'no',
            'autoResponseEncodeMediaRanges' => 'text/html',
            'customUserDirective' => 42
        ));
        
        $this->assertTrue($cfg->get('applyRouteShortcuts'));
        $this->assertTrue($cfg->get('autoResponseStatus'));
        $this->assertTrue($cfg->get('autoResponseDate'));
        $this->assertTrue($cfg->get('autoResponseContentLength'));
        $this->assertFalse($cfg->get('autoResponseEncode'));
        $this->assertEquals(array('text/html'), $cfg->get('autoResponseEncodeMediaRanges'));
        $this->assertEquals(42, $cfg->get('customUserDirective'));
    }
    
    /**
     * @covers Artax\Framework\Config\Config::populate
     * @expectedException InvalidArgumentException
     */
    public function testPopulateThrowsExceptionOnInvalidIterableParameter() {
        $validator = $this->getMock('Artax\\Framework\\Config\\ConfigValidator');
        $cfg = new Config($validator);
        $cfg->populate('not iterable');
    }
}
