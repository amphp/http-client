<?php

use Artax\Injection\Provider,
    Artax\Injection\ReflectionPool;

class ProviderTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     */
    public function testMakeInjectsSimpleConcreteDependency() {
    
        $dp = new Provider(new ReflectionPool);
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $dp->make('TestNeedsDep')
        );
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     */
    public function testMakePassesNullIfDefaultAndNoTypehintExists() {
    
        $dp = new Provider(new ReflectionPool);
        $nullCtorParamObj = $dp->make('ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertEquals(NULL, $nullCtorParamObj->arg);
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     */
    public function testMakeReturnsSharedInstanceIfSpecified() {
    
        $dp = new Provider(new ReflectionPool);
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $dp->share('RequiresInterface');
        $injected = $dp->make('RequiresInterface');
        
        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';
        
        $injected2 = $dp->make('RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     * @expectedException Artax\Injection\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnNonNullScalarTypehintSansDefinitions() {
    
        $dp = new Provider(new ReflectionPool);
        $dp->make('TestClassWithNoCtorTypehints');
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     * @expectedException Artax\Injection\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionIfProvisioningMissingUnloadableClass() {
    
        $dp = new Provider(new ReflectionPool);
        $dp->make('ClassThatDoesntExist');
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     */
    public function testMakeUsesInstanceDefinitionParamIfSpecified() {
    
        $dp = new Provider(new ReflectionPool);
        $dp->make('TestMultiDepsNeeded', array('TestDependency', new TestDependency2));
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     */
    public function testMakeUsesCustomDefinitionIfSpecified() {
    
        $dp = new Provider(new ReflectionPool);
        $dp->define('TestNeedsDep', array('testDep'=>'TestDependency'));
        $injected = $dp->make('TestNeedsDep', array('testDep'=>'TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     * @expectedException Artax\Injection\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnScalarDefaultCtorParam() {
    
        $dp  = new Provider(new ReflectionPool);
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass');
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     */
    public function testMakeStoresShareIfMarkedWithNullInstance() {
    
        $dp = new Provider(new ReflectionPool);
        $dp->share('TestDependency');
        $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     */
    public function testMakeUsesReflectionForUnknownParamsInMultiBuildWithDeps() {
    
        $dp  = new Provider(new ReflectionPool);
        $obj = $dp->make('TestMultiDepsWithCtor', array('val1'=>'TestDependency'));
        $this->assertInstanceOf('TestMultiDepsWithCtor', $obj);
        
        $obj = $dp->make('NoTypehintNoDefaultConstructorClass',
            array('val1'=>'TestDependency')
        );
        $this->assertInstanceOf('NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertEquals(NULL, $obj->testParam);
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     * @expectedException Artax\Injection\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnUnknownParamsInMultiBuildWithDeps() {
    
        $dp  = new Provider(new ReflectionPool);
        $obj = $dp->make('NoTypehintNullDefaultConstructorClass',
            array('val1'=>'TestDependency')
        );
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::getInjectedInstance
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     * @covers Artax\Injection\Provider::isInstantiable
     * @expectedException Artax\Injection\ProviderDefinitionException
     */
    public function testMakeThrowsExceptionOnUninstantiableTypehintWithoutDefinition() {
    
        $dp  = new Provider(new ReflectionPool);
        $obj = $dp->make('RequiresInterface');
    }
    
    /**
     * @covers Artax\Injection\Provider::define
     * @covers Artax\Injection\Provider::getDefinition
     */
    public function testDefineAssignsPassedDefinition() {
        
        $dp = new Provider(new ReflectionPool);
        $definition = array('dep' => 'DepImplementation');
        $dp->define('RequiresInterface', $definition);
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
        $this->assertEquals($definition, $dp->getDefinition('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::defineAll
     * @expectedException InvalidArgumentException
     */
    public function testDefineAllThrowsExceptionOnInvalidIterable() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->defineAll(1);
    }
    
    /**
     * @covers Artax\Injection\Provider::getDefinition
     * @expectedException OutOfBoundsException
     */
    public function testGetDefinitionThrowsExceptionOnUndefinedClass() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->getDefinition('ClassThatHasntBeenDefined');
    }
    
    /**
     * @covers Artax\Injection\Provider::clearAllDefinitions
     */
    public function testClearAllDefinitionsRemovesDefinitions() {
        
        $dp = new Provider(new ReflectionPool);
        $this->assertFalse($dp->isDefined('RequiresInterface'));
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $this->assertTrue($dp->isDefined('RequiresInterface'));
        $dp->clearAllDefinitions();
        $this->assertFalse($dp->isDefined('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::defineAll
     */
    public function testDefineAllAssignsPassedDefinitionsAndReturnsAddedCount() {
        
        $dp = new Provider(new ReflectionPool);
        $depList = array();
        $depList['RequiresInterface'] = array('dep' => 'DepImplementation');
        
        $this->assertEquals(1, $dp->defineAll($depList));
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::clearDefinition
     */
    public function testClearDefinitionRemovesDefinitionAndReturnsNull() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $this->assertTrue($dp->isDefined('RequiresInterface'));
        $this->assertEquals(null, $dp->clearDefinition('RequiresInterface'));
        $this->assertFalse($dp->isDefined('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::clearAllDefinitions
     */
    public function testClearAllDefinitionsRemovesDefinitionAndReturnsNull() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        $this->assertTrue($dp->isDefined('RequiresInterface'));
        
        $return = $dp->clearAllDefinitions();
        $this->assertEquals(null, $dp->clearAllDefinitions());
        $this->assertFalse($dp->isDefined('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::refresh
     */
    public function testRefreshClearsSharedInstanceAndReturnsNull() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->share('TestDependency');
        $obj = $dp->make('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        $obj->testProp = 42;
        
        $this->assertEquals(null, $dp->refresh('TestDependency'));
        $this->assertTrue($dp->isShared('TestDependency'));
        $refreshedObj = $dp->make('TestDependency');
        $this->assertEquals('testVal', $refreshedObj->testProp);
    }
    
    /**
     * @covers Artax\Injection\Provider::isShared
     */
    public function testIsSharedReturnsBooleanStatus() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->share('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        $dp->unshare('TestDependency');
        $this->assertFalse($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Injection\Provider::unshare
     */
    public function testUnshareRemovesSharingAndReturnsNull() { 
    
        $dp = new Provider(new ReflectionPool);
        $this->assertFalse($dp->isShared('TestDependency'));
        $dp->share('TestDependency');
        $this->assertTrue($dp->isShared('TestDependency'));
        $this->assertEquals(null, $dp->unshare('TestDependency'));
        $this->assertFalse($dp->isShared('TestDependency'));
    }
    
    /**
     * @covers Artax\Injection\Provider::isDefined
     */
    public function testIsDefinedReturnsDefinitionStatus() {
    
        $dp = new Provider(new ReflectionPool);
        $this->assertFalse($dp->isDefined('RequiresInterface'));
        $dp->define('RequiresInterface', array('dep' => 'DepImplementation'));
        
        $this->assertTrue($dp->isDefined('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::share
     */
    public function testShareStoresSharedInstanceAndReturnsNull() {
        
        $dp = new Provider(new ReflectionPool);
        $testShare = new StdClass;
        $testShare->test = 42;
        
        $this->assertEquals(null, $dp->share('StdClass', $testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $dp->make('stdclass')->test);
        
    }
    
    /**
     * @covers Artax\Injection\Provider::share
     */
    public function testShareMarksClassSharedOnNullObjectParameter() {
        
        $dp = new Provider(new ReflectionPool);
        $this->assertEquals(null, $dp->share('Artax\\Events\\Mediator'));
        $this->assertTrue($dp->isShared('Artax\Events\Mediator'));
    }
    
    /**
     * @covers Artax\Injection\Provider::share
     * @expectedException InvalidArgumentException
     */
    public function testShareThrowsExceptionOnInvalidArgument() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->share('Artax\\Events\\Mediator', new StdClass);
    }
    
    /**
     * @covers Artax\Injection\Provider::implement
     * @covers Artax\Injection\Provider::getImplementation
     * @covers Artax\Injection\Provider::isImplemented
     */
    public function testImplementAssignsValueAndReturnsNull() {
        
        $dp = new Provider(new ReflectionPool);
        $this->assertEquals(null, $dp->implement('DepInterface', 'DepImplementation'));
        $this->assertTrue($dp->isImplemented('DepInterface'));
        $this->assertEquals('DepImplementation', $dp->getImplementation('DepInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::implementAll
     * @expectedException InvalidArgumentException
     */
    public function testImplementAllThrowsExceptionOnNonIterableParameter() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->implementAll('not iterable');
    }
    
    /**
     * @covers Artax\Injection\Provider::implementAll
     */
    public function testImplementAllAssignsPassedImplementationsAndReturnsAddedCount() {
        
        $dp = new Provider(new ReflectionPool);
        $implementations = array(
            'DepInterface' => 'DepImplementation',
            'AnotherInterface' => 'AnotherImplementation'
        );
        
        $this->assertEquals(2, $dp->implementAll($implementations));
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::clearAllImplementations
     */
    public function testClearAllImplementationsRemovesImplementations() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->implement('DepInterface', 'DepImplementation');
        $this->assertTrue($dp->isImplemented('DepInterface'));
        $dp->clearAllImplementations();
        $this->assertFalse($dp->isImplemented('DepInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::clearImplementation
     * @covers Artax\Injection\Provider::isImplemented
     */
    public function testClearImplementationRemovesAssignedTypeAndReturnsNull() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->implement('DepInterface', 'DepImplementation');
        $this->assertTrue($dp->isImplemented('DepInterface'));
        $this->assertEquals(null, $dp->clearImplementation('DepInterface'));
        $this->assertFalse($dp->isImplemented('DepInterface'));
    }
    
    /**
     * @covers Artax\Injection\Provider::getImplementation
     * @expectedException OutOfBoundsException
     */
    public function testGetImplementationThrowsExceptionIfSpecifiedImplementationDoesntExist() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->getImplementation('InterfaceThatIsNotSetWithAnImplementation');
    }
    
    /**
     * @covers Artax\Injection\Provider::make
     * @covers Artax\Injection\Provider::buildNewInstanceArgs
     */
    public function testMakeUsesImplementationDefinitionsAsNeeded() {
        
        $dp = new Provider(new ReflectionPool);
        $dp->implement('DepInterface', 'DepImplementation');
        $this->assertInstanceOf('RequiresInterface', $dp->make('RequiresInterface'));
    }
}

class TestDependency {
    public $testProp = 'testVal';
}

class TestDependency2 extends TestDependency {
    public $testProp = 'testVal2';
}

class SpecdTestDependency extends TestDependency {
    public $testProp = 'testVal';
}

class TestNeedsDep {
    public function __construct(TestDependency $testDep) {
        $this->testDep = $testDep;
    }
}

class TestClassWithNoCtorTypehints {
    public function __construct($val = 42) {
        $this->test = $val;
    }
}

class TestMultiDepsNeeded {
    public function __construct(TestDependency $val1, TestDependency2 $val2) {
        $this->testDep = $val1;
        $this->testDep = $val2;
    }
}


class TestMultiDepsWithCtor {
    public function __construct(TestDependency $val1, TestNeedsDep $val2) {
        $this->testDep = $val1;
        $this->testDep = $val2;
    }
}

class NoTypehintNullDefaultConstructorClass {
    public $testParam = 1;
    public function __construct(TestDependency $val1, $arg=42) {
        $this->testParam = $arg;
    }
}

class NoTypehintNoDefaultConstructorClass {
    public $testParam = 1;
    public function __construct(TestDependency $val1, $arg = NULL) {
        $this->testParam = $arg;
    }
}

interface DepInterface {}

class DepImplementation implements DepInterface {
    public $testProp = 'something';
}

class RequiresInterface {
    public $dep;
    public function __construct(DepInterface $dep) {
        $this->testDep = $dep;
    }
}

class ProvTestNoDefinitionNullDefaultClass {
    public function __construct($arg = NULL) {
        $this->arg = $arg;
    }
}
