<?php

class MediatorTest extends PHPUnit_Framework_TestCase
{
    public function testBeginsEmpty()
    {
        $m = new MediatorTestImplementationClass;
        $this->assertEquals([], $m->listeners);
        return $m;
    }
    
    /**
     * @covers Artax\Events\Mediator::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnUncallableListener()
    {
        $m = new MediatorTestImplementationClass;
        $listeners = $m->push('test.event1', new StdClass);
    }
    
    /**
     * @covers Artax\Events\Mediator::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnInvalidLazyDef()
    {
        $m = new MediatorTestImplementationClass;
        $listeners = $m->push('test.event1', 'string', new StdClass);
    }
    
    /**
     * @covers Artax\Events\Mediator::push
     * @covers Artax\Events\Mediator::last
     */
    public function testPushAddsEventListenerAndReturnsCount()
    {
        $m = new MediatorTestImplementationClass;
        
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        
        $this->assertEquals(1, $m->push('test_event', $f1));
        $this->assertEquals(2, $m->push('test_event', $f2));        
        $this->assertEquals($f1, $m->listeners['test_event'][0]);
        $this->assertEquals($f2, $m->listeners['test_event'][1]);
        
        return $m;
    }
  
    /**
     * @covers Artax\Events\Mediator::pushAll
     * @expectedException InvalidArgumentException
     */
    public function testPushAllThrowsExceptionOnNonTraversableParameter()
    {
        $m = new MediatorTestImplementationClass;
        $m->pushAll('not traversable');
    }
    
    /**
     * @covers Artax\Events\Mediator::pushAll
     */
    public function testPushAllAddsNestedListenersFromTraversableParameter()
    {
        $m = new MediatorTestImplementationClass;
        $cnt = $m->pushAll([
            'app.ready' => function(){},
            'app.event' => [function(){}, function(){}, function(){}],
            'app.lazy'  => ['dot.notation', ['_shared'=>TRUE]]
        ]);
        $this->assertEquals(5, $cnt);
        $this->assertEquals(1, $m->count('app.ready'));
        $this->assertEquals(3, $m->count('app.event'));
    }
    
    /**
     * @covers Artax\Events\Mediator::unshift
     * @expectedException InvalidArgumentException
     */
    public function testUnshiftThrowsExceptionOnInvalidLazyDef()
    {
        $m = new MediatorTestImplementationClass;
        $listeners = $m->unshift('test.event1', 'string', 1);
    }
    
    /**
     * @covers Artax\Events\Mediator::unshift
     * @covers Artax\Events\Mediator::first
     */
    public function testUnshiftAddsEventListenerAndReturnsCount()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $listeners = $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $listeners);
        
        $listeners = $m->unshift('test.event1', function() { return 42; });
        $this->assertEquals(2, $listeners);
        $this->assertEquals(function() { return 42; }, $m->first('test.event1'));
        return $m;
    }
    
    /**
     * @covers Artax\Events\Mediator::unshift
     * @expectedException InvalidArgumentException
     */
    public function testUnshiftThrowsExceptionOnUncallableListener()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $listeners = $m->unshift('test.event1', 1);
    }
    
    /**
     * @covers Artax\Events\Mediator::first
     */
    public function testFirstReturnsNullIfNoListenersMatch()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $this->assertEquals(NULL, $m->first('test.event1'));
    }
    
    /**
     * @covers Artax\Events\Mediator::last
     */
    public function testLastReturnsNullIfNoListenersMatch()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $this->assertEquals(NULL, $m->last('test.event1'));
    }
    
    /**
     * @covers  Artax\Events\Mediator::count
     */
    public function testCountReturnsNumberOfListenersForSpecifiedEvent()
    {
        $m = new MediatorTestImplementationClass;
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $m->push('test.event1', $f1);
        $m->push('test.event1', $f2);
        
        $this->assertEquals(2, $m->count('test.event1'));
    }
    
    /**
     * @covers  Artax\Events\Mediator::keys
     */
    public function testKeysReturnsArrayOfListenedForEvents()
    {
        $m = new MediatorTestImplementationClass;
        $m->push('test.event1', function() { return 42; });
        $m->push('test.event2', function() { return 42; });
        $this->assertEquals(['test.event1', 'test.event2'], $m->keys());
        return $m;
    }
    
    /**
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Events\Mediator::clear
     */
    public function testClearRemovesAllListenersAndListenedForEvents($m)
    {
        $m->clear('test.event2');
        $this->assertEquals(['test.event1'], $m->keys());
        
        $m->clear();
        $this->assertEquals([], $m->keys());
    }
    
    /**
     * @covers  Artax\Events\Mediator::pop
     */
    public function testPopRemovesLastListenerForSpecifiedEvent()
    {
        $m = new MediatorTestImplementationClass;
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $m->push('test.event1', $f1);
        $m->push('test.event1', $f2);
        $popped = $m->pop('test.event1');
        $this->assertEquals($f2, $popped);
        $this->assertEquals(1, $m->count('test.event1'));
    }
    
    /**
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Events\Mediator::pop
     */
    public function testPopReturnsNullIfNoEventsMatchSpecifiedEvent($m)
    {
        $listener = $m->pop('test.eventDoesntExist');
        $this->assertEquals(NULL, $listener);
    }
    
    /**
     * @covers  Artax\Events\Mediator::shift
     */
    public function testShiftRemovesFirstListenerForSpecifiedEvent()
    {
        $m = new MediatorTestImplementationClass;
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $m->push('test.event1', $f1);
        $m->push('test.event1', $f2);
        $listener = $m->shift('test.event1');
        $this->assertEquals($f1, $listener);
        $this->assertEquals(1, $m->count('test.event1'));
    }
    
    /**
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Events\Mediator::shift
     */
    public function testShiftReturnsNullIfNoEventsMatchSpecifiedEvent($m)
    {
        $listener = $m->shift('test.eventDoesntExist');
        $this->assertEquals(NULL, $listener);
    }
    
    /**
     * @covers  Artax\Events\Mediator::unshift
     */
    public function testUnshiftCreatesEventQueueIfNotExists()
    {
        $m = new MediatorTestImplementationClass;
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $this->assertEquals(1, $m->push('test.event1', $f1));
        $this->assertEquals(1, $m->unshift('test.event2', $f2));
        $this->assertEquals(['test.event1', 'test.event2'], $m->keys());
    }
    
    /**
     * @covers  Artax\Events\Mediator::notify
     */
    public function testNotifyDistributesMessagesToListeners()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $this->assertEquals(0, $m->notify('no.listeners.event'));
        
        $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $m->notify('test.event1'));
        
        $m->push('test.event2', function() { return FALSE; });
        $m->push('test.event2', function() { return TRUE; });
        $this->assertEquals(1, $m->notify('test.event2'));
        
        $m->push('multiarg.test', function($arg1, $arg2, $arg3){});
        $this->assertEquals(1, $m->notify('multiarg.test', 1, 2, 3));
    }
    
    /**
     * @covers  Artax\Events\Mediator::all
     */
    public function testAllReturnsEventSpecificListIfSpecified()
    {
        $m = new MediatorTestImplementationClass;
        $f = function() { return TRUE; };
        $m->push('test.event1', $f);
        
        $this->assertEquals([$f], $m->all('test.event1'));
    }
    
    /**
     * @covers  Artax\Events\Mediator::notify
     */
    public function testNotifyUsesProviderForBasicLazyListenerLoad()
    {
        $m = new MediatorTestImplementationClass;        
        $m->push('class_listener_event', 'MediatorTestNeedsDep');
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers  Artax\Events\Mediator::notify
     */
    public function testNotifyUsesProviderForAdvancedLazyListenerLoad()
    {
        $m = new MediatorTestImplementationClass;        
        $m->push('class_listener_event', 'MediatorTestNeedsDep', ['MediatorTestDependency']);
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
}

class MediatorTestImplementationClass extends Artax\Events\Mediator
{
    use MagicTestGetTrait;
}

class MediatorTestDependency
{
    public $testProp = 'testVal';
}

class MediatorTestNeedsDep
{
    public $testDep;
    public function __construct(MediatorTestDependency $testDep)
    {
        $this->testDep = $testDep;
    }
    
    public function __invoke($arg1)
    {
        echo $arg1;
    }
}










