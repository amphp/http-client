<?php

class ClassLoaderFactoryTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\ClassLoaderFactory::make
   */
  public function testMakeCreatedExpectedObject()
  {
    $obj = new artax\ClassLoaderFactory;
    $return = $obj->make('invalid', 'ns1');
    $this->assertEquals(new artax\ClassLoader('ns1'), $return);
    $return = $obj->make('standard', 'ns2');
    $this->assertEquals(new artax\ClassLoader('ns2'), $return);
  }
}
