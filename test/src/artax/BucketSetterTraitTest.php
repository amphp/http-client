<?php

class BucketSettersTraitTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\BucketSettersTrait::set
   */
  public function testSetUsesSetterMethodIfSpecified()
  {
    $b = new BucketSettersTraitImplementationClass();
    $b->set('noSetterProp', 'myVal');
    $this->assertEquals('myVal', $b['noSetterProp']);
    
    $b = new BucketSettersTraitImplementationClass();
    $b->set('testProp', 'myVal');
    $this->assertEquals('myVal-appended-by-setter', $b['testProp']);
  }
  
  /**
   * @covers artax\BucketSettersTrait::add
   */
  public function testAddUsesSetterMethodIfSpecified()
  {
    $b = new BucketSettersTraitImplementationClass();
    $b->add('testProp', 'myVal');
    $this->assertEquals('myVal-appended-by-setter', $b['testProp']);
  }
}

class BucketSettersTraitImplementationClass extends artax\Bucket
{
  use artax\BucketSettersTrait;
  
  public function setTestProp($val)
  {
    $this->params['testProp'] = "$val-appended-by-setter";
    return $this;
  }
}
