<?php

use Artax\Provider,
    Artax\ReflectionCache;

class ProviderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @covers Artax\Provider::getDepsSansDefinition
     * @covers Artax\Provider::getDepsWithDefinition
     */
    public function testMakeInjectsSimpleConcreteDeps()
    {
        $dp = new Provider(new ReflectionCache);
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $dp->make('TestNeedsDep')
        );
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @covers Artax\Provider::getDepsSansDefinition
     */
    public function testMakePassesNullIfDefaultAndNoTypehintExists()
    {
        $dp = new Provider(new ReflectionCache);
        $nullCtorParamObj = $dp->make('NoDefinitionNullDefault');
        $this->assertEquals(new NoDefinitionNullDefault, $nullCtorParamObj);
        $this->assertEquals(NULL, $nullCtorParamObj->arg);
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @covers Artax\Provider::getDepsWithDefinition
     */
    public function testMakeReturnsSharedInstanceIfSpecified()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $dp->share('RequiresInterface');
        $injected = $dp->make('RequiresInterface');
        
        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';
        
        $injected2 = $dp->make('RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getDepsSansDefinition
     * @covers Artax\Provider::getDepsWithDefinition
     * @expectedException Artax\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnNonNullScalarTypehintSansDefinitions()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->make('TestClassWithNoCtorTypehints');
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @expectedException Artax\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionIfProvisioningMissingUnloadableClass()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->make('ClassThatDoesntExist');
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @covers Artax\Provider::getDepsWithDefinition
     */
    public function testMakeUsesInstanceDefinitionParamIfSpecified()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->make('TestMultiDepsNeeded', array('TestDependency', new TestDependency2));
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @covers Artax\Provider::getDepsWithDefinition
     */
    public function testMakeUsesCustomDefinitionIfSpecified()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->define('TestNeedsDep', array('testDep'=>'TestDependency'));
        $injected = $dp->make('TestNeedsDep', array('testDep'=>'TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getInjectedInstance
     * @covers Artax\Provider::getDepsSansDefinition
     * @expectedException Artax\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnScalarDefaultCtorParam()
    {
        $dp  = new Provider(new ReflectionCache);
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass');
    }
    
    /**
     * @covers Artax\Provider::make
     */
    public function testMakeStoresShareIfMarkedWithNullInstance()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->share('TestDependency');
        $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getDepsWithDefinition
     */
    public function testMakeUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $dp  = new Provider(new ReflectionCache);
        $obj = $dp->make('TestMultiDepsWithCtor', array('val1'=>'TestDependency'));
        $this->assertInstanceOf('TestMultiDepsWithCtor', $obj);
        
        $obj = $dp->make('NoTypehintNoDefaultConstructorClass',
            array('val1'=>'TestDependency')
        );
        $this->assertInstanceOf('NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertEquals(NULL, $obj->testParam);
    }
    
    /**
     * @covers Artax\Provider::make
     * @covers Artax\Provider::getDepsWithDefinition
     * @expectedException Artax\ProviderDefinitionException
     */
    public function testThrowsExceptionOnUnknownParamsInMultiBuildWithDeps()
    {
        $dp  = new Provider(new ReflectionCache);
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass',
            array('val1'=>'TestDependency')
        );
    }
    
    /**
     * @covers Artax\Provider::define
     */
    public function testDefineAssignsPassedDefinition()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Provider::defineAll
     * @expectedException InvalidArgumentException
     */
    public function testDefineAllThrowsExceptionOnInvalidIterable()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->defineAll(1);
    }
    
    /**
     * @covers Artax\Provider::defineAll
     */
    public function testDefineAllAssignsPassedDefinitionsAndReturnsAddedCount()
    {
        $dp = new Provider(new ReflectionCache);
        $depList = array();
        $depList['RequiresInterface'] = array('dep' => 'DepImplementation');
        
        $this->assertEquals(1, $dp->defineAll($depList));
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Provider::remove
     */
    public function testRemoveClearsDefinitionAndSharedInstanceAndReturnsProvider()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $return = $dp->remove('TestDependency');
        
        $this->assertFalse($dp->isShared('TestDependency'));
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @covers Artax\Provider::removeAll
     */
    public function testRemoveAllClearsDefinitionAndSharedInstancesAndReturnsProvider()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        
        $return = $dp->removeAll();
        $this->assertFalse($dp->isShared('TestDependency'));
        $this->assertEquals($dp, $dp->removeAll());
    }
    
    /**
     * @covers Artax\Provider::refresh
     */
    public function testRefreshClearsSharedInstancesAndReturnsProvider()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        $obj->testProp = 42;
        
        $this->assertEquals($dp, $dp->refresh('TestDependency'));
        $refreshedObj = $dp->make('TestDependency');
        $this->assertEquals('testVal', $refreshedObj->testProp);
    }
    
    /**
     * @covers Artax\Provider::isShared
     */
    public function testIsSharedReturnsSharedStatus()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->share('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Provider::isDefined
     */
    public function testIsDefinedReturnsDefinedStatus()
    {
        $dp = new Provider(new ReflectionCache);
        $this->assertFalse($dp->isDefined('RequiresInterface'));
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        
        $this->assertTrue($dp->isDefined('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Provider::share
     */
    public function testShareStoresSharedDependencyAndReturnsChainableInstance()
    {
        $dp = new Provider(new ReflectionCache);
        $testShare = new StdClass;
        $testShare->test = 42;
        
        $this->assertEquals($dp, $dp->share('StdClass', $testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $dp->make('stdclass')->test);
        
    }
    
    /**
     * @covers Artax\Provider::share
     */
    public function testShareMarksClassSharedOnNoObjectParameter()
    {
        $dp = new Provider(new ReflectionCache);
        $this->assertEquals($dp, $dp->share('Artax\\Mediator'));
        $this->assertTrue($dp->isShared('Artax\Mediator'));
    }
    
    /**
     * @covers Artax\Provider::share
     * @expectedException InvalidArgumentException
     */
    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $dp = new Provider(new ReflectionCache);
        $dp->share('Artax\\Mediator', new StdClass);
    }
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

interface DepInterface {}
class DepImplementation implements DepInterface
{
    public $testProp = 'something';
}
class RequiresInterface
{
    public $dep;
    
    public function __construct(DepInterface $dep)
    {
        $this->testDep = $dep;
    }
}

class NoDefinitionNullDefault
{
    public function __construct($arg = NULL)
    {
        $this->arg = $arg;
    }
}
