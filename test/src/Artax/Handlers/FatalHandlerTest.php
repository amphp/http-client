<?php

class FatalHandlerTest extends PHPUnit_Framework_TestCase
{
  public function testBeginsEmpty()
  {
    $obj = new FatalHandlerTestImplementation;
    $this->assertTrue($obj->debug);
    return $obj;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers  Artax\Handlers\FatalHandler::setDebug
   */
  public function testSetDebugAssignsPropertyValue($obj)
  {
    $obj->setDebug(FALSE);
    $this->assertFalse($obj->debug);
    return $obj;
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::shutdown
   * @covers Artax\Handlers\FatalHandler::getFatalErrException
   * @covers Artax\Handlers\FatalHandler::lastError
   */
  public function testShutdownInvokesExHandlerOnFatalError()
  {
    $lastErr = [
      'type'    => 1,
      'message' => 'The black knight always triumphs!',
      'file'    => '/path/to/file.php',
      'line'    => 42
    ];
    $stub = $this->getMock('FatalHandlerTestImplementation',
      ['exHandler', 'lastError', 'defaultHandlerMsg']);
    $stub->expects($this->any())
         ->method('lastError')
         ->will($this->returnValue($lastErr));
    $stub->shutdown();
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::defaultHandlerMsg
   */
  public function testDefaultHandlerMsgReturnsExpectedString()
  {
    $obj = new FatalHandlerTestImplementation;
    $obj->setDebug(FALSE);
    ob_start();
    $obj->exHandler(new \Exception('test'));
    $output = ob_get_contents();
    ob_end_clean();
    $expected = "Yikes. There's an internal error and we're working to fix it.";
    $this->assertEquals($expected, $output);
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::setMediator
   */
  public function testSetMediatorAssignsPassedProperty()
  {
    $med = new Artax\Events\Mediator;
    $obj = new FatalHandlerTestImplementation;
    $obj->setMediator($med);
    $this->assertEquals($med, $obj->mediator);
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::lastError
   */
  public function testLastErrorReturnsNullOnNoFatalPHPError()
  {
    $obj = new FatalHandlerTestImplementation;
    $this->assertEquals(NULL, $obj->getFatalErrException());
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::shutdown
   */
  public function testShutdownNotifiesListenersIfMediatorExists()
  {
    $medStub = $this->getMock('Artax\Events\Mediator', ['all', 'keys']);
    $obj = $this->getMock('FatalHandlerTestImplementation', ['getFatalErrException']);
    $obj->expects($this->once())
        ->method('getFatalErrException')
        ->will($this->returnValue(NULL));

    $obj->setMediator($medStub);
    
    $obj->shutdown();
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::exHandler
   */
  public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
  {
    $obj = new Artax\Handlers\FatalHandler();
    $this->assertEquals(NULL, $obj->exHandler(new Artax\Exceptions\ScriptHaltException));
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::exHandler
   */
  public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
  {
    $stub = $this->getMock('Artax\Handlers\FatalHandler', ['defaultHandlerMsg']);
    $stub->expects($this->once())
         ->method('defaultHandlerMsg')
         ->with($this->equalTo(new Exception))
         ->will($this->returnValue(NULL));
    $stub->exHandler(new Exception);
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::exHandler
   */
  public function testExHandlerNotifiesMediatorOnUncaughtException()
  {
    $e = new Exception;
    $stub = $this->getMock('Artax\Handlers\FatalHandler', ['notify']);
    $stub->expects($this->once())
         ->method('notify')
         ->with($this->equalTo('app.exception'), $this->equalTo($e));
    $stub->setMediator(new Artax\Events\Mediator);
    $stub->exHandler($e);
  }
  
  /**
   * @covers Artax\Handlers\FatalHandler::exHandler
   */
  public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
  {
    $e = new Exception;
    $stub = $this->getMock('Artax\Handlers\FatalHandler',
      ['notify', 'defaultHandlerMsg']);
    $stub->expects($this->once())
         ->method('notify')
         ->will($this->throwException($e));
    $stub->expects($this->once())
         ->method('defaultHandlerMsg')
         ->with($this->equalTo($e))
         ->will($this->returnValue(NULL));
    $stub->setMediator(new Artax\Events\Mediator);
    $stub->exHandler($e);
  }
}



class FatalHandlerTestImplementation extends Artax\Handlers\FatalHandler
{
  use MagicTestGetTrait;
}
