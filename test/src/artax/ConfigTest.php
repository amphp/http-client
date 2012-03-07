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
  public function testBeginsEmpty()
  {
    $c = new ConfigTestCoverageImplementation;
    $defaults = [
      'debug'       => FALSE,
      'classLoader' => 'standard',
      'deps'        => [],
      'listeners'   => [],
      'routes'      => []
    ];
    $this->assertEquals($defaults, $c->defaults);
  }
  
  /**
   * @covers artax\Config::filterBool
   * @covers artax\Config::setDebug
   */
  public function testFilterBoolSanitizesBoolInput()
  {
    $params = ['debug'=>0];
    $c = (new ConfigTestCoverageImplementation())->load($params);    
    $this->assertEquals(FALSE, $c->get('debug'));
  }
}

class ConfigTestCoverageImplementation extends artax\Config
{
  use MagicTestGetTrait;
}
