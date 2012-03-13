<?php

class ProviderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Ioc\Provider::__construct
     */
    public function testBeginsEmpty()
    {
        $dn = new Artax\Ioc\DotNotation;
        $dp = new ProviderCoverageTest($dn);
        $this->assertEquals([], $dp->definitions);
        $this->assertEquals([], $dp->shared);
        $this->assertEquals($dn, $dp->dotNotation);
        return $dp;
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::getDepsSansDefinition
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     */
    public function testMakeInjectsSimpleConcreteDeps($dp)
    {
        $injected = $dp->make('TestNeedsDep');
        $this->assertEquals($injected, new TestNeedsDep(new TestDependency));
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     */
    public function testMakeReturnsSharedInstanceIfSpecified($dp)
    {
        $dp->removeAll();
        $dp->define('TestNeedsDep', ['TestDependency']);
        $dp->define('TestDependency', ['_shared' => TRUE]);
        $injected = $dp->make('TestNeedsDep');
        $injected->testDep->testProp = 'something else';
        
        $injected2 = $dp->make('TestNeedsDep');
        $this->assertEquals('something else', $injected2->testDep->testProp);
        
        $shared = $dp->make('TestDependency');
        $this->assertEquals($injected->testDep, $shared);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @expectedException InvalidArgumentException
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     */
    public function testMakeThrowsExceptionOnInvalidDefinition($dp)
    {
        $dp->removeAll();
        $dp->define('TestNeedsDep', ['TestDependency']);
        $injected = $dp->make('TestNeedsDep', 'badDefinition');
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getDepsSansDefinition
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     * @expectedException InvalidArgumentException
     */
    public function testMakeThrowsExceptionOnConstructorMissingTypehintsSansDefinitions($dp)
    {
        $dp->removeAll();
        $dp->make('TestClassWithNoCtorTypehints');
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     * @expectedException InvalidArgumentException
     */
    public function testMakeThrowsExceptionOnMissingDefinitionParams($dp)
    {
        $dp->removeAll();
        $dp->make('TestMultiDepsNeeded', ['TestDependency']);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     * @expectedException InvalidArgumentException
     */
    public function testMakeThrowsExceptionOnDefinitionParamOfIncorrectType($dp)
    {
        $dp->removeAll();
        $dp->make('TestMultiDepsNeeded', ['TestDependency', new StdClass]);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     */
    public function testMakeUsesInstanceDefinitionParamIfSpecified($dp)
    {
        $dp->removeAll();
        $dp->make('TestMultiDepsNeeded', ['TestDependency', new TestDependency2]);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::getDepsWithDefinition
     */
    public function testMakeUsesCustomDefinitionIfSpecified($dp)
    {
        $dp->removeAll();
        $dp->define('TestNeedsDep', ['TestDependency']);
        $injected = $dp->make('TestNeedsDep', ['TestDependency2']);
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::define
     */
    public function testDefineAssignsPassedDefinition($dp)
    {
        $dp->removeAll();
        $dp->define('TestNeedsDep', ['TestDependency']);
        $dp->define('TestDependency', ['_shared' => TRUE]);
        $this->assertEquals($dp->definitions['TestNeedsDep'], ['TestDependency']);
        $this->assertEquals($dp->definitions['TestDependency'], ['_shared' => TRUE]);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::define
     */
    public function testDefineRemovesSharedInstanceIfNewDefinitionIsNotShared($dp)
    {
        $dp->removeAll();
        $dp->define('TestDependency', ['_shared' => TRUE]);        
        $obj = $dp->make('TestDependency');
        $dp->define('TestDependency', ['_shared' => FALSE]);
        $this->assertEmpty($dp->shared);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::define
     * @expectedException InvalidArgumentException
     */
    public function testDefineThrowsExceptionOnInvalidDefinition($dp)
    {
        $dp->define('TestNeedsDep', 1);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::defineAll
     * @expectedException InvalidArgumentException
     */
    public function testDefineAllThrowsExceptionOnInvalidIterable($dp)
    {
        $dp->defineAll(1);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::defineAll
     */
    public function testDefineAllAssignsPassedDefinitionsAndReturnsAddedCount($dp)
    {
        $dp->removeAll();
        $depList = [];
        $depList['TestNeedsDep'] = ['TestDependency'];
        $depList['TestDependency'] = ['_shared' => TRUE];
        
        $this->assertEquals(2, $dp->defineAll($depList));
        $this->assertEquals(['TestDependency'],
            $dp->definitions['TestNeedsDep']
        );
        $this->assertEquals(['_shared'=>TRUE], $dp->definitions['TestDependency']);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::remove
     */
    public function testRemoveClearsDefinitionAndSharedInstanceAndReturnsProvider($dp)
    {
        $dp->removeAll();
        $dp->define('TestDependency', ['_shared' => TRUE]);
        $obj = $dp->make('TestDependency');
        $return = $dp->remove('TestDependency');
        $this->assertEmpty($dp->shared);
        $this->assertFalse(isset($dp->definitions['TestDependency']));
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::removeAll
     */
    public function testRemoveAllClearsDefinitionAndSharedInstancesAndReturnsProvider($dp)
    {
        $dp->removeAll();
        $dp->define('TestDependency', ['_shared' => TRUE]);
        $obj = $dp->make('TestDependency');
        $this->assertEquals($dp->definitions['TestDependency'], ['_shared' => TRUE]);
        $this->assertEquals($dp->shared['TestDependency'], $obj);
        
        $return = $dp->removeAll();
        $this->assertEmpty($dp->shared);
        $this->assertEmpty($dp->definitions);
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::refresh
     */
    public function testRefreshClearsSharedInstancesAndReturnsProvider($dp)
    {
        $dp->removeAll();
        $dp->define('TestDependency', ['_shared' => TRUE]);
        $obj = $dp->make('TestDependency');
        $this->assertEquals($dp->shared['TestDependency'], $obj);
        
        $return = $dp->refresh('TestDependency');
        $this->assertEmpty($dp->shared);
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::offsetSet
     */
    public function testOffsetSetCallsDefine($dp)
    {
        $stub = $this->getMock('Artax\Ioc\Provider', ['define'],
            [new Artax\Ioc\DotNotation]
        );
        $stub->expects($this->once())
             ->method('define');
        $stub['TestNeedsDep'] = ['_shared'=>TRUE];
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::offsetUnset
     */
    public function testOffsetUnsetCallsRemove($dp)
    {
        $stub = $this->getMock('Artax\Ioc\Provider', ['remove'],
            [new Artax\Ioc\DotNotation]
        );
        $stub->expects($this->once())
             ->method('remove');
        $stub['TestNeedsDep'] = ['_shared'=>TRUE];
        unset($stub['TestNeedsDep']);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::offsetExists
     */
    public function testOffsetExistsReturnsExpected($dp)
    {
        $dp->removeAll();
        $dp->define('TestNeedsDep', ['TestDependency']);
        $obj = $dp->make('TestNeedsDep');
        $this->assertTrue(isset($dp['TestNeedsDep']));
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Ioc\Provider::offsetGet
     */
    public function testOffsetGetReturnsExpected($dp)
    {
        $dp->removeAll();
        $dp->define('TestNeedsDep', ['TestDependency']);
        $obj = $dp->make('TestNeedsDep');
        $this->assertEquals($dp->definitions['TestNeedsDep'], $dp['TestNeedsDep']);
    }
}

class ProviderCoverageTest extends Artax\Ioc\Provider
{
    use MagicTestGetTrait;
}

class TestDependency
{
    public $testProp = 'testVal';
}

class TestDependency2 extends TestDependency
{
    public $testProp = 'testVal2';
}

class SpecdTestDependency extends TestDependency
{
    public $testProp = 'testVal';
}

class TestNeedsDep
{
    public function __construct(TestDependency $testDep)
    {
        $this->testDep = $testDep;
    }
}

class TestClassWithNoCtorTypehints
{
    public function __construct($val)
    {
        $this->test = $val;
    }
}

class TestMultiDepsNeeded
{
    public function __construct(TestDependency $val1, TestDependency2 $val2)
    {
        $this->testDep = $val1;
        $this->testDep = $val2;
    }
}
