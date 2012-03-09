<?php

class TerminationTest extends PHPUnit_Framework_TestCase
{
  public function testBeginsEmpty()
  {
      $obj = new TerminationTestImplementation;
      $this->assertTrue($obj->debug);
  }
  
  /**
   * @covers Artax\Handlers\Termination::__construct
   */
  public function testConstructorInitializesDebugProperty()
  {
      $obj = new TerminationTestImplementation(0);
      $this->assertEmpty($obj->debug);
  }
  
  /**
   * @covers Artax\Handlers\Termination::shutdown
   * @covers Artax\Handlers\Termination::getFatalErrException
   * @covers Artax\Handlers\Termination::lastError
   */
  public function testShutdownInvokesExHandlerOnFatalError()
  {
    $lastErr = [
      'type'    => 1,
      'message' => 'The black knight always triumphs!',
      'file'    => '/path/to/file.php',
      'line'    => 42
    ];
    $stub = $this->getMock('TerminationTestImplementation',
      ['exHandler', 'lastError', 'defaultHandlerMsg']);
    $stub->expects($this->any())
         ->method('lastError')
         ->will($this->returnValue($lastErr));
    $stub->shutdown();
  }
  
  /**
   * @covers Artax\Handlers\Termination::defaultHandlerMsg
   */
  public function testDefaultHandlerMsgReturnsExpectedString()
  {
    $obj = new TerminationTestImplementation(FALSE);
    ob_start();
    $obj->exHandler(new \Exception('test'));
    $output = ob_get_contents();
    ob_end_clean();
    $expected = "Well this is embarrassing ... It seems we've encountered an "
               ."internal error. We're working to get it fixed.";
    $this->assertEquals($expected, $output);
  }
  
  /**
   * @covers Artax\Handlers\Termination::setMediator
   */
  public function testSetMediatorAssignsPassedProperty()
  {
    $med = new Artax\Events\Mediator;
    $obj = new TerminationTestImplementation;
    $obj->setMediator($med);
    $this->assertEquals($med, $obj->mediator);
  }
  
  /**
   * @covers Artax\Handlers\Termination::lastError
   */
  public function testLastErrorReturnsNullOnNoFatalPHPError()
  {
    $obj = new TerminationTestImplementation;
    $this->assertEquals(NULL, $obj->getFatalErrException());
  }
  
  /**
   * @covers Artax\Handlers\Termination::shutdown
   */
  public function testShutdownNotifiesListenersIfMediatorExists()
  {
    $medStub = $this->getMock('Artax\Events\Mediator', ['all', 'keys']);
    $obj = $this->getMock('TerminationTestImplementation', ['getFatalErrException']);
    $obj->expects($this->once())
        ->method('getFatalErrException')
        ->will($this->returnValue(NULL));

    $obj->setMediator($medStub);
    
    $obj->shutdown();
  }
  
  /**
   * @covers Artax\Handlers\Termination::exHandler
   */
  public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
  {
    $obj = new Artax\Handlers\Termination();
    $this->assertEquals(NULL, $obj->exHandler(new Artax\Exceptions\ScriptHaltException));
  }
  
  /**
   * @covers Artax\Handlers\Termination::exHandler
   */
  public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
  {
    $stub = $this->getMock('Artax\Handlers\Termination', ['defaultHandlerMsg']);
    $stub->expects($this->once())
         ->method('defaultHandlerMsg')
         ->with($this->equalTo(new Exception))
         ->will($this->returnValue(NULL));
    $stub->exHandler(new Exception);
  }
  
  /**
   * @covers Artax\Handlers\Termination::exHandler
   */
  public function testExHandlerNotifiesMediatorOnUncaughtException()
  {
    ob_start();
    $stub = $this->getMock('Artax\Handlers\Termination', ['notify']);
    $stub->expects($this->once())
         ->method('notify')
         ->with($this->equalTo('exception'), $this->equalTo(new Exception));
    
    $med = new Artax\Events\Mediator;
    $med->push('exception', function(){});    
    $stub->setMediator($med);
    $stub->exHandler(new Exception);
    ob_end_clean();
  }
  
  /**
   * @covers Artax\Handlers\Termination::exHandler
   */
  public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
  {
    $e = new Exception;
    $stub = $this->getMock('Artax\Handlers\Termination', [
        'notify',
        'defaultHandlerMsg'
    ]);
    $stub->expects($this->once())
         ->method('notify')
         ->will($this->throwException($e));
    $stub->expects($this->once())
         ->method('defaultHandlerMsg')
         ->with($this->equalTo($e))
         ->will($this->returnValue(NULL));
    
    $med = new Artax\Events\Mediator;
    $med->push('exception', function(){});
    $stub->setMediator($med);
    $stub->exHandler($e);
  }
}



class TerminationTestImplementation extends Artax\Handlers\Termination
{
  use MagicTestGetTrait;
}
