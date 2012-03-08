<?php

class ClassLoaderFactoryTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\ClassLoaderFactory::make
   */
  public function testMakeCreatedExpectedObject()
  {
    $obj = new Artax\ClassLoaderFactory;
    $return = $obj->make('invalid', 'ns1');
    $this->assertEquals(new Artax\ClassLoader('ns1'), $return);
    $return = $obj->make('standard', 'ns2');
    $this->assertEquals(new Artax\ClassLoader('ns2'), $return);
  }
}
