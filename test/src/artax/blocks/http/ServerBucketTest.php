<?php

class ServerBucketTest extends PHPUnit_Framework_TestCase
{
  public function testStartsEmpty()
  {
    $sb = new artax\blocks\http\ServerBucket;
    $this->assertEquals($_SERVER, $sb->all());
    return $sb;
  }
  
  /**
   * @depends testStartsEmpty
   * @covers artax\blocks\http\ServerBucket::detect
   */
  public function testDetectLoadesServerSuperglobal($sb)
  {
    $_SERVER['TESTVAL'] = 'test';    
    $sb->detect();
    $this->assertEquals('test', $sb['TESTVAL']);
    unset($_SERVER['TESTVAL']);
  }
}
