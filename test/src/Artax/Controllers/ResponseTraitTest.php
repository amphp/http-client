<?php

class ResponseControllerTraitTest extends PHPUnit_Framework_TestCase
{  
  public function testBeginsEmpty()
  {
    $rt = new ResponseTraitTestImplentation;
    $this->assertEquals(NULL, $rt->response);
    return $rt;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Controllers\ResponseControllerTrait::setResponse
   * @covers Artax\Controllers\ResponseControllerTrait::getResponse
   */
  public function testGetResponseReturnsProperty($rt)
  {
    $response = $this->getMock('Artax\Controllers\Response');
    $rt->setResponse($response);
    $this->assertEquals($response, $rt->getResponse());
  }
}

class ResponseTraitTestImplentation
{
  use MagicTestGetTrait, Artax\Controllers\ResponseControllerTrait;
}
