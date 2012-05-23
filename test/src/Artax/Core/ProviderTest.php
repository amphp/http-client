<?php

use Artax\Core\Provider;

class ProviderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsSansDefinition
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeInjectsSimpleConcreteDeps()
    {
        $dp = new Provider;
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $dp->make('TestNeedsDep')
        );
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsSansDefinition
     */
    public function testMakePassesNullIfDefaultAndNoTypehintExists()
    {
        $dp = new Provider;
        $nullCtorParamObj = $dp->make('NoDefinitionNullDefault');
        $this->assertEquals(new NoDefinitionNullDefault, $nullCtorParamObj);
        $this->assertEquals(NULL, $nullCtorParamObj->arg);
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeReturnsSharedInstanceIfSpecified()
    {
        $dp = new Provider;
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $dp->share('RequiresInterface');
        $injected = $dp->make('RequiresInterface');
        
        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';
        
        $injected2 = $dp->make('RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getDepsSansDefinition
     * @covers Artax\Core\Provider::getDepsWithDefinition
     * @expectedException Artax\Core\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnNonNullScalarTypehintSansDefinitions()
    {
        $dp = new Provider;
        $dp->make('TestClassWithNoCtorTypehints');
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @expectedException Artax\Core\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionIfProvisioningMissingUnloadableClass()
    {
        $dp = new Provider;
        $dp->make('ClassThatDoesntExist');
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeUsesInstanceDefinitionParamIfSpecified()
    {
        $dp = new Provider;
        $dp->make('TestMultiDepsNeeded', array('TestDependency', new TestDependency2));
    }
    
    /**
     * @covers Artax\Core\Provider::make
     * @covers Artax\Core\Provider::getInjectedInstance
     * @covers Artax\Core\Provider::getDepsWithDefinition
     */
    public function testMakeUsesCustomDefinitionIfSpecified()
    {
        $dp = new Provider;
        $dp->define('TestNeedsDep', array('testDep'=>'TestDependency'));
        $injected = $dp->make('TestNeedsDep', array('testDep'=>'TestDependency2'));
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
        $dp  = new Provider;
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass');
    }
    
    /**
     * @covers Artax\Core\Provider::make
     */
    public function testMakeStoresShareIfMarkedWithNullInstance()
    {
        $dp = new Provider;
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
        $dp  = new Provider;
        $obj = $dp->make('TestMultiDepsWithCtor', array('val1'=>'TestDependency'));
        $this->assertInstanceOf('TestMultiDepsWithCtor', $obj);
        
        $obj = $dp->make('NoTypehintNoDefaultConstructorClass',
            array('val1'=>'TestDependency')
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
        $dp  = new Provider;
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass',
            array('val1'=>'TestDependency')
        );
    }
    
    /**
     * @covers Artax\Core\Provider::define
     */
    public function testDefineAssignsPassedDefinition()
    {
        $dp = new Provider;
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Core\Provider::defineAll
     * @expectedException InvalidArgumentException
     */
    public function testDefineAllThrowsExceptionOnInvalidIterable()
    {
        $dp = new Provider;
        $dp->defineAll(1);
    }
    
    /**
     * @covers Artax\Core\Provider::defineAll
     */
    public function testDefineAllAssignsPassedDefinitionsAndReturnsAddedCount()
    {
        $dp = new Provider;
        $depList = array();
        $depList['RequiresInterface'] = array('dep' => 'DepImplementation');
        
        $this->assertEquals(1, $dp->defineAll($depList));
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Core\Provider::remove
     */
    public function testRemoveClearsDefinitionAndSharedInstanceAndReturnsProvider()
    {
        $dp = new Provider;
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $return = $dp->remove('TestDependency');
        
        $this->assertFalse($dp->isShared('TestDependency'));
        $this->assertEquals($return, $dp);
    }
    
    /**
     * @covers Artax\Core\Provider::removeAll
     */
    public function testRemoveAllClearsDefinitionAndSharedInstancesAndReturnsProvider()
    {
        $dp = new Provider;
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        
        $return = $dp->removeAll();
        $this->assertFalse($dp->isShared('TestDependency'));
        $this->assertEquals($dp, $dp->removeAll());
    }
    
    /**
     * @covers Artax\Core\Provider::refresh
     */
    public function testRefreshClearsSharedInstancesAndReturnsProvider()
    {
        $dp = new Provider;
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        $obj->testProp = 42;
        
        $this->assertEquals($dp, $dp->refresh('TestDependency'));
        $refreshedObj = $dp->make('TestDependency');
        $this->assertEquals('testVal', $refreshedObj->testProp);
    }
    
    /**
     * @covers Artax\Core\Provider::isShared
     */
    public function testIsSharedReturnsSharedStatus()
    {
        $dp = new Provider;
        $dp->share('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Core\Provider::isDefined
     */
    public function testIsDefinedReturnsDefinedStatus()
    {
        $dp = new Provider;
        $this->assertFalse($dp->isDefined('RequiresInterface'));
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        
        $this->assertTrue($dp->isDefined('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Core\Provider::share
     */
    public function testShareStoresSharedDependencyAndReturnsChainableInstance()
    {
        $dp = new Provider;
        $testShare = new StdClass;
        $testShare->test = 42;
        
        $this->assertEquals($dp, $dp->share('StdClass', $testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $dp->make('stdclass')->test);
        
    }
    
    /**
     * @covers Artax\Core\Provider::share
     */
    public function testShareMarksClassSharedOnNoObjectParameter()
    {
        $dp = new Provider;
        $this->assertEquals($dp, $dp->share('Artax\\Core\\Mediator'));
        $this->assertTrue($dp->isShared('Artax\Core\Mediator'));
    }
    
    /**
     * @covers Artax\Core\Provider::share
     * @expectedException InvalidArgumentException
     */
    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $dp = new Provider;
        $dp->share('Artax\\Core\\Mediator', new StdClass);
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
