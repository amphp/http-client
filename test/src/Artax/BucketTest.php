<?php

class BucketTest extends PHPUnit_Framework_TestCase
{
  public function testBeginsEmpty()
  {
    $bucket = new Artax\Bucket;
    $this->assertEmpty($bucket->all());
  }
  
  /**
   * @covers Artax\Bucket::load
   */
  public function testLoadAssignsChildClassDefaultsIfSpecified()
  {
    $bucket = new BucketDefaultsTestClass;
    $defaults = $bucket->defaults;
    $this->assertEmpty($bucket->all());
    $bucket->load([]);
    $this->assertEquals($defaults, $bucket->all());
  }
  
    
  /**
   * @covers Artax\Bucket::all
   * @covers Artax\Bucket::clear
   */
  public function testConstructorInitializesArrayParamsOnInstantiation()
  {
    $c = new Artax\Bucket();
    
    $data = ['test'=>new stdClass];
    $c = (new Artax\Bucket())->load($data);
    $this->assertEquals($data, $c->all());
  }
  
  /**
   * @covers Artax\Bucket::exists
   */
  public function testExistsReturnsIfContainerHasSpecifiedEntity()
  {
    $c = new Artax\Bucket();
    $this->assertFalse($c->exists('testEntity'));
    
    $c->load(['testEntity'=>new stdClass]);
    $this->assertTrue($c->exists('testEntity'));
  }
  
  /**
   * @covers Artax\Bucket::get
   * @expectedException OutOfBoundsException
   */
  public function testGetThrowsExceptionOnInvalidParameter()
  {
    $c = new Artax\Bucket();
    $this->assertFalse($c->exists('testEntity'));
    $bad = $c->get('testEntity');
  }
  
  /**
   * @covers Artax\Bucket::all
   * @covers Artax\Bucket::clear
   * @covers Artax\Bucket::load
   */
  public function testClearRemovesAllContainedParameters()
  {
    $c = new Artax\Bucket();

    $data = ['test'=>new stdClass];
    $c->load($data);
    $this->assertEquals($data, $c->all());
    
    $c->clear();
    $this->assertEquals([], $c->all());
  }
  
  /**
   * @covers Artax\Bucket::set
   * @covers Artax\Bucket::get
   * @covers Artax\Bucket::clear
   */
  public function testSetAssignsSpecifiedParamValueIfValid()
  {
    $c = new Artax\Bucket();
    
    $data = new stdClass;
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
    
    $data = function() { return TRUE; };
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
    
    $data = [];
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
  }
  
  /**
   * @covers Artax\Bucket::add
   */
  public function testAddOnlyAssignsSpecifiedParamValueIfNotAlreadyExisting()
  {
    $c = new Artax\Bucket();
    
    $data = new stdClass;
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
    
    $c->add('test', 'secondary_data');
    $this->assertEquals($data, $c->get('test'));
    
    $c->add('test2', 'should_work');
    $this->assertEquals('should_work', $c->get('test2'));
  }
  
  /**
   * @covers Artax\Bucket::remove
   * @covers Artax\Bucket::clear
   * @covers Artax\Bucket::set
   * @covers Artax\Bucket::get
   */
  public function testRemoveDeletesSpecifiedBucketParam()
  {
    $c = new Artax\Bucket();
    
    $data = new stdClass;
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
    
    $c->remove('test');
    $this->assertEquals([], $c->all());
    $this->assertEquals(FALSE, $c->exists('test'));
  }
  
  /**
   * @covers Artax\Bucket::offsetSet
   * @covers Artax\Bucket::offsetGet
   * @covers Artax\Bucket::offsetExists
   * @covers Artax\Bucket::offsetUnset
   */
  public function testBucketArrayAccessFunctionsAsExpected()
  {
    $c = new Artax\Bucket();
    
    $data = new stdClass;
    $c['test'] = $data;
    $this->assertEquals($data, $c['test']);
    
    $this->assertTrue(isset($c['test']));
    
    unset($c['test']);
    $this->assertEquals(FALSE, $c->exists('test'));
    $this->assertEquals([], $c->all());
  }
  
  /**
   * @covers Artax\Bucket::rewind
   * @covers Artax\Bucket::current
   * @covers Artax\Bucket::key
   * @covers Artax\Bucket::next
   * @covers Artax\Bucket::valid
   */
  public function testIteratorAccessFunctionsAsExpected()
  {
    $c = new Artax\Bucket;
    
    $data = ['param1'=>new stdClass, 'param2'=>[], 'param3'=>new stdClass];
    $c = (new Artax\Bucket)->load($data);
    
    $this->assertEquals(new stdClass, $c->current(), 'Bucket::current failed ' .
      'to correctly return the value of array pointer\'s current position');
    
    $this->assertEquals('param1', $c->key(), 'Bucket::key failed to correctly ' .
      'return the array pointer\'s current key');
      
    $c->next();
    
    $this->assertEquals('param2', $c->key(), 'Bucket::next failed to move ' .
      'the array pointer forward');
    
    $c->next();
    $c->next();
    
    $this->assertEquals(FALSE, $c->valid(), 'Bucket::valid failed to return ' .
      'FALSE when the end of the array was reached');
    
    $c->rewind();
    
    $this->assertEquals(new stdClass, $c->current(), 'Bucket::rewind failed ' .
      'to correctly reset the array pointer the starting position');
  }
  
  /**
   * @covers Artax\Bucket::keys
   */
  public function testKeysReturnsListOfBucketParamNames()
  {
    $c = new Artax\Bucket();
    $c->set('testProp',  'myVal');
    $c->set('testProp2', 'myVal');
    $this->assertEquals(['testProp','testProp2'], $c->keys());
  }
  
}

class BucketDefaultsTestClass extends Artax\Bucket
{
  use MagicTestGetTrait;
  
  public function __construct()
  {
    $this->defaults = [
      'param1' => 'val1',
      'param2' => 'val2'
    ];
  }
}
