<?php

use Artax\Framework\Config\Config,
    Artax\Framework\Config\ConfigValidator;

class ConfigValidatorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Config\ConfigValidator::validate
     * @covers Artax\Framework\Config\ConfigValidator::validateRoutes
     * @expectedException Artax\Framework\Config\ConfigException
     */
    public function testValidateThrowsExceptionIfNoRoutesSpecified() {
        $directives = array();
        $cfg = new Config();
        $cfg->populate($directives);
        
        $validator = new ConfigValidator();
        $validator->validate($cfg);
    }
    
    /**
     * @covers Artax\Framework\Config\ConfigValidator::validate
     * @covers Artax\Framework\Config\ConfigValidator::validateRoutes
     * @expectedException Artax\Framework\Config\ConfigException
     */
    public function testValidateThrowsExceptionIfNonIterableRoutesSpecified() {
        $directives = array(
            'routes' => 'not iterable'
        );
        
        $cfg = new Config();
        $cfg->populate($directives);
        
        $validator = new ConfigValidator();
        $validator->validate($cfg);
    }
    
    /**
     * @covers Artax\Framework\Config\ConfigValidator::validate
     * @covers Artax\Framework\Config\ConfigValidator::validateRoutes
     */
    public function testValidateDoesntThrowIfRoutesAreValid() {
        $directives = new StdClass;
        $directives->routes = array(
            '/' => 'Resources\\Index'
        );
        $cfg = new Config();
        $cfg->populate($directives);
        
        $validator = new ConfigValidator();
        $validator->validate($cfg);
    }
}
