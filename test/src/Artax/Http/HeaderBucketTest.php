<?php

class HeaderBucketTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Http\BucketAbstract::__construct
   */
  public function testIsInitiallyEmpty()
  {
    $hb = new Artax\Http\HeaderBucket;
    $this->assertAttributeEmpty('params', $hb);
  }
  
  /**
   * @covers Artax\Http\HeaderBucket::detect
   * @covers Artax\Http\HeaderBucket::getRequestHeaders
   * @covers Artax\Http\HeaderBucket::formatHeaderNames
   * @covers Artax\Http\HeaderBucket::nativeHeaderGet
   */
  public function testDetectAutoLoadsBucketParams()
  {
    $_SERVER['HTTP_TEST_HEADER'] = 'test';
    $_SERVER['CONTENT_TYPE']     = 'text/html';
    $_SERVER['CONTENT_LENGTH']   = 100;
    $hb = (new Artax\Http\HeaderBucket())->detect();
    
    $this->assertEquals('test', $hb['Test-Header']);
    $this->assertEquals('text/html', $hb['Content-Type']);
    $this->assertEquals(100, $hb['Content-Length']);
    
    unset($_SERVER['HTTP_TEST_HEADER']);
    unset($_SERVER['CONTENT_TYPE']);
    unset($_SERVER['CONTENT_LENGTH']);
  }
  
  /**
   * @covers Artax\Http\HeaderBucket::getRequestHeaders
   * @covers Artax\Http\HeaderBucket::nativeHeaderGet
   */
  public function testDetectNativelyRetrievesHeadersIfAvailable()
  {
    $mock = $this->getMock('Artax\Http\HeaderBucket', ['nativeHeaderGet']);
    $mock->expects($this->once())
         ->method('nativeHeaderGet')
         ->will($this->returnValue(['Test-Header'=>'test']));
    $mock->detect();
    $this->assertEquals(['Test-Header'=>'test'], $mock->all());
  }
}

?>
