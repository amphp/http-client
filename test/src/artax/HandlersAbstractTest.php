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
  
  /**
   * @covers artax\HandlersAbstract::shutdown
   */
  public function testShutdownCallsUnexpectedErrorAsExpected()
  {
    $ha = $this->getMock('HandlersAbstractTestClass', ['getFatalErrException']);
    $ha->expects($this->any())
               ->method('getFatalErrException')
               ->will($this->returnValue(FALSE));
    $ha->shutdown();
    
    $ha = $this->getMock('HandlersAbstractTestClass', ['getFatalErrException']);
    $ha->expects($this->any())
               ->method('getFatalErrException')
               ->will($this->returnValue(new \Exception));
    $ha->shutdown();
  }
  
  /**
   * artax\HandlersAbstract::getFatalErrException
   */
  public function testGetFatalErrExceptionReturnsExOnFatalError()
  {
    $lastErr = [
      'type'    => 1,
      'message' => 'Fatal Error: uscSucks',
      'file'    => '/my/sweet/script.php',
      'line'    => 42
    ];
    $ha = $this->getMock('HandlersAbstractTestClass', ['lastError']);
    $ha->expects($this->any())
       ->method('lastError')
       ->will($this->returnValue($lastErr));
    
    $this->assertTrue($ha->getFatalErrException() 
      instanceof artax\exceptions\RuntimeException);
  }
  
  /**
   * artax\HandlersAbstract::getFatalErrException
   * artax\HandlersAbstract::lastError
   */
  public function testGetFatalErrExceptionReturnsNullOnNonFatalError()
  {
    $handlers = new HandlersAbstractTestClass;
    $this->assertNull($handlers->getFatalErrException());
  }
}

class HandlersAbstractTestClass extends artax\HandlersAbstract
{
  public function notFound()
  {
  }
  public function unexpectedError(\Exception $e)
  {
  }
}














