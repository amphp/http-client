<?php

class ProviderFactoryTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Ioc\ProviderFactory::make
   */
  public function testMakeReturnsInjectedProviderInstance()
  {
    $dp = (new Artax\Ioc\ProviderFactory)->make();
    $this->assertInstanceOf('Artax\Ioc\Provider', $dp);
  }
}
