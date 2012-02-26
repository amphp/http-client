<?php

class ClassLoaderAbstractTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\ClassLoaderAbstract::__construct
   * @covers artax\ClassLoaderAbstract::getNamespace
   */
  public function testBeginsEmpty()
  {
    $obj = $this->getMockForAbstractClass('artax\ClassLoaderAbstract');
    $this->assertEquals(NULL, $obj->getNamespace());
    $this->assertEquals(NULL, $obj->getIncludePath());
    return $obj;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\ClassLoaderAbstract::getNsSeparator
   * @covers artax\ClassLoaderAbstract::setNsSeparator
   */
  public function testSetNsSeparatorAssignsProperty($obj)
  {
    $obj->setNsSeparator('_');
    $this->assertEquals('_', $obj->getNsSeparator());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\ClassLoaderAbstract::getIncludePath
   * @covers artax\ClassLoaderAbstract::setIncludePath
   */
  public function testSetIncludePathAssignsProperty($obj)
  {
    $obj->setIncludePath('/my/path');
    $this->assertEquals('/my/path', $obj->getIncludePath());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\ClassLoaderAbstract::getExt
   * @covers artax\ClassLoaderAbstract::setExt
   */
  public function testSetExtAssignsProperty($obj)
  {
    $obj->setExt('/my/path');
    $this->assertEquals('/my/path', $obj->getExt());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\ClassLoaderAbstract::register
   * @covers artax\ClassLoaderAbstract::unregister
   */
  public function testRegisterAndUnregister($obj)
  {
    $this->assertTrue($obj->register());
    $this->assertTrue($obj->unregister());
  }  
}
