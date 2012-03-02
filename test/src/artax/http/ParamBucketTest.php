<?php

class ParamBucketTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\http\ParamBucket::detect
   * @covers artax\http\ParamBucket::__construct
   */
  public function testDetectMergesGETandPOSTSuperglobals()
  {
    $pb = new artax\http\ParamBucket;
    
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
