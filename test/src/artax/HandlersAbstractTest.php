<?php

class HandlersAbstractTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\HandlersAbstract::getDebug
   */
  public function testBeginsEmpty()
  {
    $ha = $this->getMockForAbstractClass('artax\HandlersAbstract');
    $this->assertFalse($ha->getDebug());
    return $ha;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\HandlersAbstract::setDebug
   */
  public function testSetDebugAssignsBoolValue($ha)
  {
    $ha->setDebug(TRUE);
    $this->assertTrue($ha->getDebug());
  }
  
  /**
   * @covers artax\HandlersAbstract::exHandler
   */
  public function testExHandlerCallsNotFoundAsExpected()
  {
    $ha = $this->getMockForAbstractClass('artax\HandlersAbstract');
    $ha->expects($this->once())
       ->method('notFound');
    $ha->exHandler(new artax\exceptions\RequestNotFoundException);
  }
  
  /**
   * @covers artax\HandlersAbstract::exHandler
   */
  public function testExHandlerCallsUnexpectedErrorAsExpected()
  {
    $ha = $this->getMockForAbstractClass('artax\HandlersAbstract');
    $ha->expects($this->once())->method('unexpectedError');
    $ha->exHandler(new Exception);
  }
}















