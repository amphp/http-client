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
   * @covers  artax\handlers\FatalHandler::setDebug
   */
  public function testSetDebugAssignsPropertyValue($obj)
  {
    $obj->setDebug(FALSE);
    $this->assertFalse($obj->debug);
    return $obj;
  }
  
  /**
   * @covers artax\handlers\FatalHandler::shutdown
   * @covers artax\handlers\FatalHandler::getFatalErrException
   * @covers artax\handlers\FatalHandler::lastError
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
   * @covers artax\handlers\FatalHandler::defaultHandlerMsg
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
   * @covers artax\handlers\FatalHandler::setMediator
   */
  public function testSetMediatorAssignsPassedProperty()
  {
    $med = new artax\events\Mediator;
    $obj = new FatalHandlerTestImplementation;
    $obj->setMediator($med);
    $this->assertEquals($med, $obj->mediator);
  }
  
  /**
   * @covers artax\handlers\FatalHandler::lastError
   */
  public function testLastErrorReturnsNullOnNoFatalPHPError()
  {
    $obj = new FatalHandlerTestImplementation;
    $this->assertEquals(NULL, $obj->getFatalErrException());
  }
  
  /**
   * @covers artax\handlers\FatalHandler::shutdown
   */
  public function testShutdownNotifiesListenersIfMediatorExists()
  {
    $medStub = $this->getMock('artax\events\Mediator', ['all', 'keys']);
    $obj = $this->getMock('FatalHandlerTestImplementation', ['getFatalErrException']);
    $obj->expects($this->once())
        ->method('getFatalErrException')
        ->will($this->returnValue(NULL));

    $obj->setMediator($medStub);
    
    $obj->shutdown();
  }
  
  /**
   * @covers artax\handlers\FatalHandler::exHandler
   */
  public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
  {
    $obj = new artax\handlers\FatalHandler();
    $this->assertEquals(NULL, $obj->exHandler(new artax\exceptions\ScriptHaltException));
  }
  
  /**
   * @covers artax\handlers\FatalHandler::exHandler
   */
  public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
  {
    $stub = $this->getMock('artax\handlers\FatalHandler', ['defaultHandlerMsg']);
    $stub->expects($this->once())
         ->method('defaultHandlerMsg')
         ->with($this->equalTo(new Exception))
         ->will($this->returnValue(NULL));
    $stub->exHandler(new Exception);
  }
  
  /**
   * @covers artax\handlers\FatalHandler::exHandler
   */
  public function testExHandlerNotifiesMediatorOnUncaughtException()
  {
    $e = new Exception;
    $stub = $this->getMock('artax\handlers\FatalHandler', ['notify']);
    $stub->expects($this->once())
         ->method('notify')
         ->with($this->equalTo('app.exception'), $this->equalTo($e));
    $stub->setMediator(new artax\events\Mediator);
    $stub->exHandler($e);
  }
  
  /**
   * @covers artax\handlers\FatalHandler::exHandler
   */
  public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
  {
    $e = new Exception;
    $stub = $this->getMock('artax\handlers\FatalHandler',
      ['notify', 'defaultHandlerMsg']);
    $stub->expects($this->once())
         ->method('notify')
         ->will($this->throwException($e));
    $stub->expects($this->once())
         ->method('defaultHandlerMsg')
         ->with($this->equalTo($e))
         ->will($this->returnValue(NULL));
    $stub->setMediator(new artax\events\Mediator);
    $stub->exHandler($e);
  }
}



class FatalHandlerTestImplementation extends artax\handlers\FatalHandler
{
  use MagicTestGetTrait;
}
