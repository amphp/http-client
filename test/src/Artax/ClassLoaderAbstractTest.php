<?php

class ClassLoaderAbstractTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\ClassLoaderAbstract::__construct
   * @covers Artax\ClassLoaderAbstract::getNamespace
   */
  public function testBeginsEmpty()
  {
    $obj = $this->getMockForAbstractClass('Artax\ClassLoaderAbstract');
    $this->assertEquals(NULL, $obj->getNamespace());
    $this->assertEquals(NULL, $obj->getIncludePath());
    return $obj;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\ClassLoaderAbstract::getNsSeparator
   * @covers Artax\ClassLoaderAbstract::setNsSeparator
   */
  public function testSetNsSeparatorAssignsProperty($obj)
  {
    $obj->setNsSeparator('_');
    $this->assertEquals('_', $obj->getNsSeparator());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\ClassLoaderAbstract::getIncludePath
   * @covers Artax\ClassLoaderAbstract::setIncludePath
   */
  public function testSetIncludePathAssignsProperty($obj)
  {
    $obj->setIncludePath('/my/path');
    $this->assertEquals('/my/path', $obj->getIncludePath());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\ClassLoaderAbstract::getExt
   * @covers Artax\ClassLoaderAbstract::setExt
   */
  public function testSetExtAssignsProperty($obj)
  {
    $obj->setExt('/my/path');
    $this->assertEquals('/my/path', $obj->getExt());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\ClassLoaderAbstract::register
   * @covers Artax\ClassLoaderAbstract::unregister
   */
  public function testRegisterAndUnregister($obj)
  {
    $this->assertTrue($obj->register());
    $this->assertTrue($obj->unregister());
  }  
}
