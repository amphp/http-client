<?php

class HeaderBucketTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\http\BucketAbstract::__construct
   */
  public function testIsInitiallyEmpty()
  {
    $hb = new artax\http\HeaderBucket;
    $this->assertAttributeEmpty('params', $hb);
  }
  
  /**
   * @covers artax\http\HeaderBucket::detect
   * @covers artax\http\HeaderBucket::getRequestHeaders
   * @covers artax\http\HeaderBucket::formatHeaderNames
   * @covers artax\http\HeaderBucket::nativeHeaderGet
   */
  public function testDetectAutoLoadsBucketParams()
  {
    $_SERVER['HTTP_TEST_HEADER'] = 'test';
    $_SERVER['CONTENT_TYPE']     = 'text/html';
    $_SERVER['CONTENT_LENGTH']   = 100;
    $hb = (new artax\http\HeaderBucket())->detect();
    
    $this->assertEquals('test', $hb['Test-Header']);
    $this->assertEquals('text/html', $hb['Content-Type']);
    $this->assertEquals(100, $hb['Content-Length']);
    
    unset($_SERVER['HTTP_TEST_HEADER']);
    unset($_SERVER['CONTENT_TYPE']);
    unset($_SERVER['CONTENT_LENGTH']);
  }
  
  /**
   * @covers artax\http\HeaderBucket::getRequestHeaders
   * @covers artax\http\HeaderBucket::nativeHeaderGet
   */
  public function testDetectNativelyRetrievesHeadersIfAvailable()
  {
    $mock = $this->getMock('artax\http\HeaderBucket', ['nativeHeaderGet']);
    $mock->expects($this->once())
         ->method('nativeHeaderGet')
         ->will($this->returnValue(['Test-Header'=>'test']));
    $mock->detect();
    $this->assertEquals(['Test-Header'=>'test'], $mock->all());
  }
}

?>
