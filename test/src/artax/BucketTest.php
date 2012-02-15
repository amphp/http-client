<?php

class BucketTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\Bucket::all
   * @covers artax\Bucket::clear
   */
  public function testConstructorInitializesArrayParamsOnInstantiation()
  {
    $c = new artax\Bucket();
    
    $data = ['test'=>new stdClass];
    $c = (new artax\Bucket())->load($data);
    $this->assertEquals($data, $c->all());
  }
  
  /**
   * @covers artax\Bucket::exists
   */
  public function testExistsReturnsIfContainerHasSpecifiedEntity()
  {
    $c = new artax\Bucket();
    $this->assertFalse($c->exists('testEntity'));
    
    $c->load(['testEntity'=>new stdClass]);
    $this->assertTrue($c->exists('testEntity'));
  }
  
  /**
   * @covers artax\Bucket::get
   * @expectedException artax\exceptions\OutOfBoundsException
   */
  public function testGetThrowsExceptionOnInvalidParameter()
  {
    $c = new artax\Bucket();
    $this->assertFalse($c->exists('testEntity'));
    $bad = $c->get('testEntity');
  }
  
  /**
   * @covers artax\Bucket::all
   * @covers artax\Bucket::clear
   * @covers artax\Bucket::load
   */
  public function testClearRemovesAllContainedParameters()
  {
    $c = new artax\Bucket();

    $data = ['test'=>new stdClass];
    $c->load($data);
    $this->assertEquals($data, $c->all());
    
    $c->clear();
    $this->assertEquals([], $c->all());
  }
  
  /**
   * @covers artax\Bucket::set
   * @covers artax\Bucket::get
   * @covers artax\Bucket::clear
   */
  public function testSetAssignsSpecifiedParamValueIfValid()
  {
    $c = new artax\Bucket();
    
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
   * @covers artax\Bucket::add
   */
  public function testAddOnlyAssignsSpecifiedParamValueIfNotAlreadyExisting()
  {
    $c = new artax\Bucket();
    
    $data = new stdClass;
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
    
    $c->add('test', 'secondary_data');
    $this->assertEquals($data, $c->get('test'));
    
    $c->add('test2', 'should_work');
    $this->assertEquals('should_work', $c->get('test2'));
  }
  
  /**
   * @covers artax\Bucket::remove
   * @covers artax\Bucket::clear
   * @covers artax\Bucket::set
   * @covers artax\Bucket::get
   */
  public function testRemoveDeletesSpecifiedBucketParam()
  {
    $c = new artax\Bucket();
    
    $data = new stdClass;
    $c->set('test', $data);
    $this->assertEquals($data, $c->get('test'));
    
    $c->remove('test');
    $this->assertEquals([], $c->all());
    $this->assertEquals(FALSE, $c->exists('test'));
  }
  
  /**
   * @covers artax\Bucket::offsetSet
   * @covers artax\Bucket::offsetGet
   * @covers artax\Bucket::offsetExists
   * @covers artax\Bucket::offsetUnset
   */
  public function testBucketArrayAccessFunctionsAsExpected()
  {
    $c = new artax\Bucket();
    
    $data = new stdClass;
    $c['test'] = $data;
    $this->assertEquals($data, $c['test']);
    
    $this->assertTrue(isset($c['test']));
    
    unset($c['test']);
    $this->assertEquals(FALSE, $c->exists('test'));
    $this->assertEquals([], $c->all());
  }
  
  /**
   * @covers artax\Bucket::rewind
   * @covers artax\Bucket::current
   * @covers artax\Bucket::key
   * @covers artax\Bucket::next
   * @covers artax\Bucket::valid
   */
  public function testIteratorAccessFunctionsAsExpected()
  {
    $c = new artax\Bucket;
    
    $data = ['param1'=>new stdClass, 'param2'=>[], 'param3'=>new stdClass];
    $c = (new artax\Bucket)->load($data);
    
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
   * @covers artax\Bucket::keys
   */
  public function testKeysReturnsListOfBucketParamNames()
  {
    $c = new artax\Bucket();
    $c->set('testProp',  'myVal');
    $c->set('testProp2', 'myVal');
    $this->assertEquals(['testProp','testProp2'], $c->keys());
  }
  
}
