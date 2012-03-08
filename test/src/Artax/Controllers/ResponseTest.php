<?php

class ResponseTest extends PHPUnit_Framework_TestCase
{  
  public function testBeginsEmpty()
  {
    $response = new Artax\Controllers\Response;
    $this->assertEquals('', $response->get());
    return $response;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Controllers\Response::set
   * @covers Artax\Controllers\Response::get
   */
  public function testSetAssignsBody($response)
  {
    $body = 'Girl, look at that body (I work out).';
    $response->set($body);
    $this->assertEquals($body, $response->get());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Controllers\Response::append
   */
  public function testAppendAddsToBody($response)
  {
    $body   = 'Girl, look at that body (I work out).';
    $append = ' Appended.';
    $response->set($body);
    $response->append($append);
    $this->assertEquals($body . $append, $response->get());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Controllers\Response::prepend
   */
  public function testPrependAddsToBody($response)
  {
    $body    = 'Girl, look at that body (I work out).';
    $prepend = ' Prepended.';
    $response->set($body);
    $response->prepend($prepend);
    $this->assertEquals($prepend . $body, $response->get());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Controllers\Response::output
   */
  public function testOutputEchoesBodyString($response)
  {
    $body = 'Girl, look at that body (I work out).';
    $response->set($body);
    $this->expectOutputString($body);
    $response->output();
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Controllers\Response::__toString
   */
  public function testToStringReturnsBodyString($response)
  {
    $body = 'Girl, look at that body (I work out).';
    $response->set($body);
    $this->assertEquals($body, (string) $response);
  }
}
