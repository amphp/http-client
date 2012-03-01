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
   * @covers artax\controllers\ResponseControllerTrait::setResponse
   * @covers artax\controllers\ResponseControllerTrait::getResponse
   */
  public function testGetResponseReturnsProperty($rt)
  {
    $response = $this->getMock('artax\controllers\Response');
    $rt->setResponse($response);
    $this->assertEquals($response, $rt->getResponse());
  }
}

class ResponseTraitTestImplentation
{
  use MagicTestGetTrait, artax\controllers\ResponseControllerTrait;
}
