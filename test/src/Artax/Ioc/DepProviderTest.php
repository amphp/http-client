<?php

class ProviderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Ioc\Provider::__construct
     */
    public function testConstructorAssignsDotNotationPropertyIfPassed()
    {
        $dp = new ProviderCoverageTest(new Artax\Ioc\DotNotation);
        $this->assertEmpty($dp->params);
        return $dp;
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     * @covers Artax\Ioc\Provider::parseConstructorArgs
     */
    public function testMakeReturnsInjectedFromReflection($dp)
    {
        $injected = $dp->make('TestNeedsDep');
        $this->assertEquals($injected, new TestNeedsDep(new TestDependency));
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     */
    public function testMakeReturnsInjectedUsingSpecifiedClassNames($dp)
    {
        $specdVals = ['testDep'=>'SpecdTestDependency'];
        $dp->add('TestNeedsDep', $specdVals);
        $injected = $dp->make('TestNeedsDep');
        $this->assertEquals($injected, new TestNeedsDep(new SpecdTestDependency));
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::make
     * @covers Artax\Ioc\Provider::getInjectedInstance
     */
    public function testMakeReturnsSharedInstanceIfSpecified($dp)
    {
        $dp->add('TestNeedsDep', ['testDep' => 'TestDependency']);
        $dp->add('TestDependency', ['_shared' => TRUE]);
        $injected = $dp->make('TestNeedsDep');
        $injected->testDep->testProp = 'something else';
        
        $injected2 = $dp->make('TestNeedsDep');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::add
     */
    public function testAddAssignsPassedDefinition($dp)
    {
        $dp->add('TestNeedsDep', ['testDep' => 'TestDependency']);
        $dp->add('TestDependency', ['_shared' => TRUE]);
        $this->assertEquals($dp->params['TestNeedsDep'], ['testDep'=>'TestDependency']);
        $this->assertEquals($dp->params['TestDependency'], ['_shared' => TRUE]);
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::add
     * @expectedException InvalidArgumentException
     */
    public function testAddThrowsExceptionOnInvalidDefinition($dp)
    {
        $dp->add('TestNeedsDep', 1);
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::addAll
     * @expectedException InvalidArgumentException
     */
    public function testAddAllThrowsExceptionOnInvalidIterable($dp)
    {
        $dp->addAll(1);
    }
    
    /**
     * @depends testConstructorAssignsDotNotationPropertyIfPassed
     * @covers Artax\Ioc\Provider::addAll
     */
    public function testAddAllAssignsPassedDefinitionsAndReturnsAddedCount($dp)
    {
        $depList = new StdClass;
        $depList->TestNeedsDep = ['testDep' => 'TestDependency'];
        $depList->TestDependency = ['_shared' => TRUE];
        
        $this->assertEquals(2, $dp->addAll($depList));
        $this->assertEquals(['testDep' => 'TestDependency'],
            $dp->params['TestNeedsDep']
        );
        $this->assertEquals(['_shared'=>TRUE], $dp->params['TestDependency']);
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
