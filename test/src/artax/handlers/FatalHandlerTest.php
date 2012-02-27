<?php

class FatalHandlerTest extends PHPUnit_Framework_TestCase
{
  public function testBeginsEmpty()
  {
    $obj = new FatalHandlerTestImplementation;
    $this->assertTrue($obj->debug);
    $this->assertNull($obj->exController);
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
   * @depends testSetDebugAssignsPropertyValue
   * @covers  artax\handlers\FatalHandler::setExController
   */
  public function testSetExControllerAssignsPropertyValue($obj)
  {
    $obj->setDebug(TRUE);
    $obj->setExController(new ExControllerImplementation);
    $this->assertTrue($obj->exController->debug);
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
    $stub = $this->getMock('FatalHandlerTestImplementation', ['exHandler', 'lastError']);
    $stub->expects($this->any())
         ->method('lastError')
         ->will($this->returnValue($lastErr));
    $stub->shutdown();
    
    $stub = $this->getMock('FatalHandlerTestImplementation', ['exHandler']);
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
   * @covers artax\handlers\FatalHandler::exHandler
   */
  public function testExHandlerDisplaysDefaultMessageIfNotCustomControllerSpecified()
  {
    $stub = $this->getMock('FatalHandlerTestImplementation', ['defaultHandlerMsg']);
    $stub->expects($this->any())
         ->method('defaultHandlerMsg')
         ->will($this->returnValue('stub string'));
         
    ob_start();
    $stub->exHandler(new \Exception('test'));
    $output = ob_get_contents();
    ob_end_clean();
    
    $this->assertEquals('stub string', $output);
  }
  
  /**
   * @depends testSetExControllerAssignsPropertyValue
   * @covers artax\handlers\FatalHandler::exHandler
   */
  public function testExHandlerInvokesCustomControllerIfSpecified($obj)
  { 
    ob_start();
    $obj->exHandler(new \Exception('test'));
    $output = ob_get_contents();
    ob_end_clean();
    
    $this->assertEquals('test', $output);
    
    $obj->setExController(new ExControllerImplementation2);
    ob_start();
    $obj->exHandler(new \Exception('test'));
    ob_end_clean();
  }
}





class FatalHandlerTestImplementation extends artax\handlers\FatalHandler
{
  use MagicTestGetTrait;
}

class ExControllerImplementation implements artax\handlers\ExControllerInterface
{
  use
    artax\handlers\ExControllerTrait,
    MagicTestGetTrait;
  
  protected $response;
  
  public function __construct()
  {
    $this->response = new ExControllerResponseImplementation;
  }
  
  public function notify($eventName)
  {
  }
  
  public function getResponse()
  {
    return $this->response;
  }
  
  public function exec()
  {
    return $this;
  }
  
  public function __invoke()
  {
    call_user_func_array([$this, 'exec'], func_get_args());
  }
}

class ExControllerImplementation2 extends ExControllerImplementation
{
  public function __construct()
  {
    $this->response = new ExControllerResponseExceptionImplementation;
  }
}

class ExControllerResponseImplementation
{
  public function exec()
  {
    echo 'test';
  }
}

class ExControllerResponseExceptionImplementation
{
  public function exec()
  {
    throw new Exception('test');
  }
}
