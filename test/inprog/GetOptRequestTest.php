<?php

class GetOptRequestTest extends BaseTest
{
  /**
   * @covers artax\GetOptRequest::__construct
   */
  public function testConstructorInitializesProtectedPropertyArrays()
  {
    $_SERVER['argv'] = array();
    $go = new artax\GetOptRequest;
    $arr = array();
    $this->assertEquals($go->available_opts, $arr);
    $this->assertEquals($go->passed_opts, $arr);
    $this->assertEquals($go->opts, $arr);
    $this->assertEquals($go->extra_args, $arr);
  }
}

?>
