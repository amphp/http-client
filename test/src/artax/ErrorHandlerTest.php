<?php

class ErrorHandlerTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\ErrorHandler::handle
   * @expectedException artax\exceptions\ErrorException
   */
  public function testHandlerThrowsErrorException()
  {
    $obj = new artax\ErrorHandler;
    $obj->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
  }
  
  /**
   * @covers artax\ErrorHandler::handle
   */
  public function testHandlerReturnsExpectedMessage()
  {
    $msg   = 'Notice: test notice message in testFile.php on line 42';
    $exMsg = '';
    $obj   = new artax\ErrorHandler;
    try {
      $obj->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
    } catch (artax\exceptions\ErrorException $e) {
      $exMsg = $e->getMessage();
    }
    $this->assertEquals($msg, $exMsg);
  }
}















