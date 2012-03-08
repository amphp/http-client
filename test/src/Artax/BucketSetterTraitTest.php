<?php

class BucketSettersTraitTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\BucketSettersTrait::set
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
   * @covers Artax\BucketSettersTrait::add
   */
  public function testAddUsesSetterMethodIfSpecified()
  {
    $b = new BucketSettersTraitImplementationClass();
    $b->add('testProp', 'myVal');
    $this->assertEquals('myVal-appended-by-setter', $b['testProp']);
  }
}

class BucketSettersTraitImplementationClass extends Artax\Bucket
{
  use Artax\BucketSettersTrait;
  
  public function setTestProp($val)
  {
    $this->params['testProp'] = "$val-appended-by-setter";
    return $this;
  }
}
