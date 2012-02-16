<?php

class RequestNotFoundExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\RequestNotFoundException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testRequestNotFoundExceptionIsArtaxException()
  {
    $e = new artax\exceptions\RequestNotFoundException();
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


