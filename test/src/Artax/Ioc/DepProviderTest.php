<?php

class DepProviderTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Ioc\DepProvider::__construct
   */
  public function testConstructorAssignsDotNotationPropertyIfPassed()
  {
    $dp = new DepProviderCoverageTest(new Artax\Ioc\DotNotation);
    $this->assertEmpty($dp->all());
    return $dp;
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\Ioc\DepProvider::make
   * @covers Artax\Ioc\DepProvider::getInjectedInstance
   * @covers Artax\Ioc\DepProvider::parseConstructorArgs
   */
  public function testMakeReturnsInjectedFromReflection($dp)
  {
    $injected = $dp->make('TestNeedsDep');
    $this->assertEquals($injected, new TestNeedsDep(new TestDependency));
  }
  
  /**
   * @depends testConstructorAssignsDotNotationPropertyIfPassed
   * @covers Artax\Ioc\DepProvider::make
   * @covers Artax\Ioc\DepProvider::getInjectedInstance
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
   * @covers Artax\Ioc\DepProvider::make
   * @covers Artax\Ioc\DepProvider::getInjectedInstance
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
   * @covers Artax\Ioc\DepProvider::make
   * @covers Artax\Ioc\DepProvider::getInjectedInstance
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
   * @depends testSetSharedDepStoresDependencyInSharedPropertyArray
   * @covers Artax\Ioc\DepProvider::clearSharedDep
   */
  public function testClearSharedDepRemovesCachedDependencyFromSharedArray($dp)
  {
    $dp->clearSharedDep('TestDependency');
    $this->assertEquals(new TestDependency, $dp->make('TestDependency'));
  }
}

class DepProviderCoverageTest extends Artax\Ioc\DepProvider
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
