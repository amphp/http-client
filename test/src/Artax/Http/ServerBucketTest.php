<?php

class ServerBucketTest extends PHPUnit_Framework_TestCase
{
  public function testStartsEmpty()
  {
    $sb = new Artax\Http\ServerBucket;
    $this->assertEquals($_SERVER, $sb->all());
    return $sb;
  }
  
  /**
   * @depends testStartsEmpty
   * @covers Artax\Http\ServerBucket::detect
   */
  public function testDetectLoadesServerSuperglobal($sb)
  {
    $_SERVER['TESTVAL'] = 'test';    
    $sb->detect();
    $this->assertEquals('test', $sb['TESTVAL']);
    unset($_SERVER['TESTVAL']);
  }
}
