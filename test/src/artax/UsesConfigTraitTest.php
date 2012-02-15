<?php

class UsesConfigTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\UsesConfigTrait::setConfig
   * @covers artax\UsesConfigTrait::getConfig
   */
  public function testSetConfigAssignsAndGetConfigReturnsObjectConfigProperty()
  {
    $test = new ConfigTraitImplementationClass;
    $this->assertEquals(NULL, $test->getConfig());
    
    $cfg = new artax\Config;
    $test->setConfig($cfg);
    $this->assertEquals($cfg, $test->getConfig());
  }
}

class ConfigTraitImplementationClass
{
  use artax\UsesConfigTrait;
}
