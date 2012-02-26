<?php

class ExControllerTraitTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\ExControllerTrait::setException
   */
  public function testSetExceptionAssignsProperty()
  {
    $ex = new Exception;
    $obj = new ExControllerTraitImplementationClass;
    $obj->setException($ex);
    $this->assertEquals($ex, $obj->getException());
  }
  
  /**
   * @covers artax\ExControllerTrait::setDebug
   */
  public function testSetDebugAssignsProperty()
  {
    $obj = new ExControllerTraitImplementationClass;
    $obj->setDebug(TRUE);
    $this->assertTrue($obj->getDebug());
  }
}

class ExControllerTraitImplementationClass
{
  use artax\ExControllerTrait;
  
  public function getDebug()
  {
    return $this->debug;
  }
  
  public function getException()
  {
    return $this->exception;
  }  
}
