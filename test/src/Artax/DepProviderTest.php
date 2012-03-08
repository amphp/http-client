<?php

class DepProviderTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\DepProvider::__construct
   */
  public function testConstructorAssignsDotNotationPropertyIfPassed()
  {
    $dp = new DepProviderCoverageTest(new Artax\DotNotation);
    $this->assertEmpty($dp->all());
    return $dp;
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\DepProvider::make
   * @covers Artax\DepProvider::getInjectedInstance
   * @covers Artax\DepProvider::parseConstructorArgs
   */
  public function testMakeReturnsInjectedFromReflection($dp)
  {
    $injected = $dp->make('TestNeedsDep');
    $this->assertEquals($injected, new TestNeedsDep(new TestDependency));
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\DepProvider::make
   * @covers Artax\DepProvider::getInjectedInstance
   */
  public function testMakeReturnsCustomObjectValsIfPassed($dp)
  {
    $dep = new TestDependency;
    $dep->testProp = 'something';
    $custom   = ['testDep'=>$dep];
    $injected = $dp->make('TestNeedsDep', $custom);
    $this->assertEquals($injected->testDep, $dep);
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\DepProvider::make
   * @covers Artax\DepProvider::getInjectedInstance
   */
  public function testMakeReturnsInjectedUsingBucketSpecifiedClassNames($dp)
  {
    $specdVals = ['testDep'=>'SpecdTestDependency'];
    $dp->set('TestNeedsDep', $specdVals);
    $injected = $dp->make('TestNeedsDep');
    $this->assertEquals($injected, new TestNeedsDep(new SpecdTestDependency));
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\DepProvider::make
   * @covers Artax\DepProvider::getInjectedInstance
   */
  public function testMakeReturnsSharedInstanceIfSpecified($dp)
  {
    $dp->set('TestNeedsDep', ['testDep' => 'TestDependency']);
    $dp->set('TestDependency', ['_shared' => TRUE]);
    $injected = $dp->make('TestNeedsDep');
    $injected->testDep->testProp = 'something else';
    
    $injected2 = $dp->make('TestNeedsDep');
    $this->assertEquals('something else', $injected2->testDep->testProp);
    
    $dp->remove('TestNeedsDep');
    
    $injected3 = $dp->make('TestNeedsDep');
    $this->assertEquals('something else', $injected3->testDep->testProp);
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\DepProvider::setSharedDep
   */
  public function testSetSharedDepStoresDependencyInSharedPropertyArray($dp)
  {
    $testDep = new TestDependency;
    $testDep->testProp = 'shared value';
    $dp->setSharedDep('TestDependency', $testDep);
    $this->assertEquals($testDep, $dp->make('TestDependency'));
    return $dp;
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\DepProvider::setSharedDep
   * @expectedException InvalidArgumentException
   */
  public function testSetSharedDepThrowsExceptionOnInstanceTypeMismatch($dp)
  {
    $dp->setSharedDep('TestDependency', new stdClass);
  }
  
  /**
   * @depends testSetSharedDepStoresDependencyInSharedPropertyArray
   * @covers Artax\DepProvider::clearSharedDep
   */
  public function testClearSharedDepRemovesCachedDependencyFromSharedArray($dp)
  {
    $dp->clearSharedDep('TestDependency');
    $this->assertEquals(new TestDependency, $dp->make('TestDependency'));
  }
}

class DepProviderCoverageTest extends Artax\DepProvider
{
}

class TestDependency
{
  public $testProp = 'testVal';
}

class SpecdTestDependency extends TestDependency
{
  public $testProp = 'testVal';
}

class TestNeedsDep
{
  public $testDep;
  public function __construct(TestDependency $testDep)
  {
    $this->testDep = $testDep;
  }
}
