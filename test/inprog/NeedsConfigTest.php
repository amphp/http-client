<?php

class UsesConfigTest extends BaseTest
{
  /**
   * @covers artax\UsesConfig::setCfg
   * @covers artax\UsesConfig::getCfg
   */
  public function testSetConfigAssignsAndGetConfigReturnsObjectConfigProperty()
  {
    $test = new ImplementationClassForUsesConfigTrait;
    $this->assertEquals(NULL, $test->getCfg());
    
    $cfg = new artax\Config;
    $test->setCfg($cfg);
    $this->assertEquals($cfg, $test->getCfg());
  }
}

class ImplementationClassForUsesConfigTrait
{
  use artax\UsesConfig;
}
