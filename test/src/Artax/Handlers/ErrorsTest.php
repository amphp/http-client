<?php

class ErrorHandlerTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Handlers\Errors::handle
   * @expectedException ErrorException
   */
  public function testHandlerThrowsErrorException()
  {
    $obj = new Artax\Handlers\Errors;
    $obj->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
  }
  
  /**
   * @covers Artax\Handlers\Errors::handle
   */
  public function testHandlerReturnsExpectedMessage()
  {
    $msg   = 'Notice: test notice message in testFile.php on line 42';
    $exMsg = '';
    $obj   = new Artax\Handlers\Errors;
    try {
      $obj->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
    } catch (ErrorException $e) {
      $exMsg = $e->getMessage();
    }
    $this->assertEquals($msg, $exMsg);
  }
}
