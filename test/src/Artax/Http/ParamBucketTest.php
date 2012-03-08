<?php

class ParamBucketTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Http\ParamBucket::detect
   * @covers Artax\Http\ParamBucket::__construct
   */
  public function testDetectMergesGETandPOSTSuperglobals()
  {
    $pb = new Artax\Http\ParamBucket;
    
    $this->assertEquals([], $pb->all());
    
    $_GET  = ['testGet'  =>4];
    $_POST = ['testPost' =>2];
    
    $pb->detect();
    $this->assertEquals(['testGet'=>4, 'testPost'=>2], $pb->all());
    
    unset($_GET['testGet']);
    unset($_POST['testPost']);
  }
}

?>
