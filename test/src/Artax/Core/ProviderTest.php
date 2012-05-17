<?php

class ProviderTest extends PHPUnit_Framework_TestCase
{
    public function testBeginsEmpty()
    {
        $dp = new ProviderCoverageTest;
        $this->assertEquals([], $dp->definitions);
        $this->assertEquals([], $dp->shared);
        return $dp;
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsSansDefinition
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeInjectsSimpleConcreteDeps()
    {
        $dp = new ProviderCoverageTest;
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $dp->make('TestNeedsDep')
        );
        
        $this->assertEquals(new AnotherOne(new AnotherTwo),
            $dp->make('AnotherOne')
        );
        
        $this->assertEquals(new NoDefinitionNullDefault,
            $dp->make('NoDefinitionNullDefault')
        );
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeReturnsSharedInstanceIfSpecified()
    {
        $dp = new ProviderCoverageTest;
        $dp->define('TestNeedsDep', ['TestDependency']);
        $dp->share('TestDependency');
        $injected = $dp->make('TestNeedsDep');
        $injected->testDep->testProp = 'something else';
        
        $injected2 = $dp->make('TestNeedsDep');
        $this->assertEquals('something else', $injected2->testDep->testProp);
        
        $shared = $dp->make('TestDependency');
        $this->assertEquals($injected->testDep, $shared);
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getDepsSansDefinition
     * @covers Artax\Core\Provider::getDepsWithDefinition
     * @expectedException Artax\Core\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnNonNullScalarTypehintSansDefinitions()
    {
        $dp = new ProviderCoverageTest;
        $dp->make('TestClassWithNoCtorTypehints');
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @expectedException Artax\Core\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionIfProvisioningMissingUnloadableClass()
    {
        $dp = new ProviderCoverageTest;
        $dp->make('ClassThatDoesntExist');
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeUsesInstanceDefinitionParamIfSpecified()
    {
        $dp = new ProviderCoverageTest;
        $dp->make('TestMultiDepsNeeded', ['TestDependency', new TestDependency2]);
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeUsesCustomDefinitionIfSpecified()
    {
        $dp = new ProviderCoverageTest;
        $dp->define('TestNeedsDep', ['testDep'=>'TestDependency']);
        $injected = $dp->make('TestNeedsDep', ['testDep'=>'TestDependency2']);
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsSansDefinition
     * @expectedException Artax\Core\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnScalarDefaultCtorParam()
    {
        $dp = new ProviderCoverageTest;
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass');
    }
    
    /**
     * @covers Artax\Core\Provider::make
     */
    public function testMakeStoresShareIfMarkedWithNullInstance()
    {
        $dp = new ProviderCoverageTest;
        $dp->share('TestDependency');
        $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $dp  = new ProviderCoverageTest;
        $obj = $dp->make('TestMultiDepsWithCtor', ['val1'=>'TestDependency']);
        $this->assertInstanceOf('TestMultiDepsWithCtor', $obj);
        
        $obj = $dp->make('NoTypehintNoDefaultConstructorClass',
            ['val1'=>'TestDependency']
        );
        $this->assertInstanceOf('NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertEquals(NULL, $obj->testParam);
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getDepsWithDefinition
     * @expectedException Artax\Core\ProviderDefinitionException
     */
    public function testThrowsExceptionOnUnknownParamsInMultiBuildWithDeps()
    {
        $dp  = new ProviderCoverageTest;
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass',
            ['val1'=>'TestDependency']
        );
    }
    
    /**
     * @covers Artax\Core\Provider::define
     */
    public function testDefineAssignsPassedDefinition()
    {
        $dp  = new ProviderCoverageTest;
        $dp->define('TestNeedsDep', ['TestDependency']);
        $dp->define('TestDependency', ['_shared']);
        $this->assertEquals($dp->definitions['testneedsdep'], ['TestDependency']);
        $this->assertEquals($dp->definitions['testdependency'], ['_shared']);
    }
    
    /**
     * @covers Artax\Core\Provider::define
     */
    public function testDefineRemovesSharedInstanceIfNewDefinitionIsNotShared()
    {
        $dp  = new ProviderCoverageTest;
        $dp->define('TestDependency', ['_shared']);        
        $obj = $dp->make('TestDependency');
        $dp->define('TestDependency', ['_shared']);
        $this->assertEmpty($dp->shared);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::defineAll
     * @expectedException InvalidArgumentException
     */
    public function testDefineAllThrowsExceptionOnInvalidIterable($dp)
    {
        $dp->defineAll(1);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::defineAll
     */
    public function testDefineAllAssignsPassedDefinitionsAndReturnsAddedCount($dp)
    {
        $dp->removeAll();
        $depList = [];
        $depList['TestNeedsDep'] = ['TestDependency'];
        $depList['TestDependency'] = ['_shared'];
        
        $this->assertEquals(2, $dp->defineAll($depList));
        $this->assertEquals(['TestDependency'], $dp->definitions['testneedsdep']);
        $this->assertEquals(['_shared'], $dp->definitions['testdependency']);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::remove
     */
    public function testRemoveClearsDefinitionAndSharedInstanceAndReturnsProvider($dp)
    {
        $dp->removeAll();
        $dp->define('TestDependency', ['_shared']);
        $obj = $dp->make('TestDependency');
        $return = $dp->remove('TestDependency');
        $this->assertEmpty($dp->shared);
        $this->assertFalse(isset($dp->definitions['TestDependency']));
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::removeAll
     */
    public function testRemoveAllClearsDefinitionAndSharedInstancesAndReturnsProvider($dp)
    {
        $dp->removeAll();
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertEquals($dp->shared['testdependency'], $obj);
        
        $return = $dp->removeAll();
        $this->assertEmpty($dp->shared);
        $this->assertEmpty($dp->definitions);
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::refresh
     */
    public function testRefreshClearsSharedInstancesAndReturnsProvider($dp)
    {
        $dp->removeAll();
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertEquals($dp->shared['testdependency'], $obj);
        
        $return = $dp->refresh('TestDependency');
        $this->assertEquals(['testdependency'=>NULL], $dp->shared);
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::isShared
     */
    public function testIsSharedReturnsSharedStatus($dp)
    {
        $dp->removeAll();
        
        $dp->share('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::isDefined
     */
    public function testIsDefinedReturnsDefinedStatus($dp)
    {
        $dp->removeAll();
        $this->assertFalse($dp->isDefined('TestDependency'));
        $dp->define('TestDependency', ['_shared']);
        $this->assertTrue($dp->isDefined('testdependency'));
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::share
     */
    public function testShareStoresSharedDependencyAndReturnsChainableInstance($dp)
    {
        $dp->removeAll();
        $testShare = new StdClass;
        
        $clsName = strtolower(get_class($testShare));
        
        $return = $dp->share('StdClass', $testShare);
        $this->assertEquals($testShare, $dp->shared[$clsName]);
        $this->assertEquals($dp, $return);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::share
     */
    public function testShareMarksClassSharedOnNoObjectParameter($dp)
    {
        $dp->removeAll();
        $this->assertEquals($dp, $dp->share('Artax\Core\Mediator'));
        $this->assertEquals(['artax\core\mediator'=>NULL], $dp->shared);
        $isShared = $dp->isShared('Artax\Core\Mediator');
        $this->assertTrue($isShared);
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Core\Provider::share
     * @expectedException InvalidArgumentException
     */
    public function testShareThrowsExceptionOnInvalidArgument($dp)
    {
        $testShare = new StdClass;
        $dp->share('Artax\Core\Mediator', $testShare);
    }
}

class ProviderCoverageTest extends Artax\Core\Provider
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
    public function __construct($val = 42)
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


class TestMultiDepsWithCtor
{
    public function __construct(TestDependency $val1, TestNeedsDep $val2)
    {
        $this->testDep = $val1;
        $this->testDep = $val2;
    }
}

class NoTypehintNullDefaultConstructorClass
{
    public $testParam = 1;
    public function __construct(TestDependency $val1, $arg=42)
    {
        $this->testParam = $arg;
    }
}

class NoTypehintNoDefaultConstructorClass
{
    public $testParam = 1;
    public function __construct(TestDependency $val1, $arg = NULL)
    {
        $this->testParam = $arg;
    }
}

class AnotherOne
{
    public function __construct(AnotherTwo $dep)
    {
    }
}

class AnotherTwo {}

class NoDefinitionNullDefault
{
    public function __construct($arg = NULL)
    {
    }
}
