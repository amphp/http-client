<?php

/**
 * The ConfigTestExt is used to circumvent an error with current PHPUnit
 * code coverage reporting for classes that use traits
 */
 
class ConfigTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\Config::__construct
   */
  public function testConstructorInitializesParamDefaults()
  {
    $c = new ConfigTestExt();
    $this->assertEquals(TRUE, $c->get('debug'));
  }
  
  /**
   * @covers artax\Config::filterBool
   * @covers artax\Config::setDebug
   * @covers artax\Config::setHttpBundle
   * @covers artax\Config::setCliBundle
   */
  public function testFilterBoolSanitizesBoolInput()
  {
    $params = ['debug'=>0, 'httpBundle'=>'Off', 'cliBundle'=> 'on'];
    $c = new ConfigTestExt($params);
    $this->assertEquals(FALSE, $c->get('debug'));
    $this->assertEquals(FALSE, $c->get('httpBundle'));
    $this->assertEquals(TRUE, $c->get('cliBundle'));
  }
}

class ConfigTestExt extends artax\Config
{
}
