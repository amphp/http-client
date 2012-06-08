<?php

use Artax\Mediator,
    Artax\Provider,
    Artax\ReflectionPool;

class MediatorTest extends PHPUnit_Framework_TestCase
{
    public function testBeginsEmpty()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        return $m;
    }
    
    /**
     * @covers Artax\Mediator::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnUncallableListener()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $listeners = $m->push('test.event1', new StdClass);
    }
    
    /**
     * @covers Artax\Mediator::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnInvalidLazyDef()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $listeners = $m->push('test.event1', 'string', new StdClass);
    }
    
    /**
     * @covers Artax\Mediator::push
     * @covers Artax\Mediator::last
     */
    public function testPushAddsEventListenerAndReturnsCount()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        
        $this->assertEquals(1, $m->push('test_event', $f1));
        $this->assertEquals(2, $m->push('test_event', $f2));        
        $this->assertEquals($f1, $m->shift('test_event'));
        $this->assertEquals($f2, $m->shift('test_event'));

    }
  
    /**
     * @covers Artax\Mediator::pushAll
     * @expectedException InvalidArgumentException
     */
    public function testPushAllThrowsExceptionOnNonTraversableParameter()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $m->pushAll('not traversable');
    }
    
    /**
     * @covers Artax\Mediator::pushAll
     */
    public function testPushAllAddsNestedListenersFromTraversableParameter()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $cnt = $m->pushAll(array(
            'app.ready' => function(){},
            'app.event' => array(function(){}, function(){}, function(){}),
            'app.lazy'  => array('dot.notation'),
            'lazy.w.def'=> array('MediatorTestNeedsDep',
                array('testDep' => new MediatorTestDependency))
        ));
        $this->assertEquals(6, $cnt);
        $this->assertEquals(1, $m->count('app.ready'));
        $this->assertEquals(3, $m->count('app.event'));
        $this->assertEquals(1, $m->count('app.lazy'));
        $this->assertEquals(1, $m->count('lazy.w.def'));
    }
    
    /**
     * @covers Artax\Mediator::unshift
     * @expectedException InvalidArgumentException
     */
    public function testUnshiftThrowsExceptionOnInvalidLazyDef()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $listeners = $m->unshift('test.event1', 'string', 1);
    }
    
    /**
     * @covers Artax\Mediator::unshift
     * @covers Artax\Mediator::first
     */
    public function testUnshiftAddsEventListenerAndReturnsCount()
    {
        $dp = new Provider(new ReflectionPool);
        $m  = new Mediator($dp);
        $listeners = $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $listeners);
        
        $listeners = $m->unshift('test.event1', function() { return 42; });
        $this->assertEquals(2, $listeners);
        $this->assertEquals(function() { return 42; }, $m->first('test.event1'));
        return $m;
    }
    
    /**
     * @covers Artax\Mediator::unshift
     * @expectedException InvalidArgumentException
     */
    public function testUnshiftThrowsExceptionOnUncallableListener()
    {
        $dp = new Provider(new ReflectionPool);
        $m  = new Mediator($dp);
        $listeners = $m->unshift('test.event1', 1);
    }
    
    /**
     * @covers Artax\Mediator::first
     */
    public function testFirstReturnsNullIfNoListenersMatch()
    {
        $dp = new Provider(new ReflectionPool);
        $m  = new Mediator($dp);
        $this->assertEquals(NULL, $m->first('test.event1'));
    }
    
    /**
     * @covers Artax\Mediator::last
     */
    public function testLastReturnsNullIfNoListenersMatch()
    {
        $dp = new Provider(new ReflectionPool);
        $m  = new Mediator($dp);
        $this->assertEquals(NULL, $m->last('test.event1'));
    }
    
    /**
     * @covers  Artax\Mediator::count
     */
    public function testCountReturnsNumberOfListenersForSpecifiedEvent()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $m->push('test.event1', $f1);
        $m->push('test.event1', $f2);
        
        $this->assertEquals(2, $m->count('test.event1'));
    }
    
    /**
     * @covers  Artax\Mediator::keys
     */
    public function testKeysReturnsArrayOfListenedForEvents()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $m->push('test.event1', function() { return 42; });
        $m->push('test.event2', function() { return 42; });
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
        
        return $m;
    }
    
    /**
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Mediator::clear
     */
    public function testClearRemovesAllListenersAndListenedForEvents($m)
    {
        $m->clear('test.event2');
        $this->assertEquals(array('test.event1'), $m->keys());
        
        $m->clear();
        $this->assertEquals(array(), $m->keys());
    }
    
    /**
     * @covers  Artax\Mediator::pop
     */
    public function testPopRemovesLastListenerForSpecifiedEvent()
    {
        $m  = new Mediator(new Provider(new ReflectionPool));
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
     * @covers  Artax\Mediator::pop
     */
    public function testPopReturnsNullIfNoEventsMatchSpecifiedEvent($m)
    {
        $listener = $m->pop('test.eventDoesntExist');
        $this->assertEquals(NULL, $listener);
    }
    
    /**
     * @covers  Artax\Mediator::shift
     */
    public function testShiftRemovesFirstListenerForSpecifiedEvent()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
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
     * @covers  Artax\Mediator::shift
     */
    public function testShiftReturnsNullIfNoEventsMatchSpecifiedEvent($m)
    {
        $listener = $m->shift('test.eventDoesntExist');
        $this->assertEquals(NULL, $listener);
    }
    
    /**
     * @covers  Artax\Mediator::unshift
     */
    public function testUnshiftCreatesEventQueueIfNotExists()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $this->assertEquals(1, $m->push('test.event1', $f1));
        $this->assertEquals(1, $m->unshift('test.event2', $f2));
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
    }
    
    /**
     * @covers  Artax\Mediator::notify
     */
    public function testNotifyDistributesMessagesToListeners()
    {
        $dp = new Provider(new ReflectionPool);
        $m = new Mediator($dp);
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
     * @covers  Artax\Mediator::all
     */
    public function testAllReturnsEventSpecificListIfSpecified()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $f = function() { return TRUE; };
        $m->push('test.event1', $f);
        
        $this->assertEquals(array($f), $m->all('test.event1'));
    }
    
    /**
     * @covers  Artax\Mediator::notify
     */
    public function testNotifyUsesProviderForBasicLazyListenerLoad()
    {
        $m = new Mediator(new Provider(new ReflectionPool));        
        $m->push('class_listener_event', 'MediatorTestNeedsDep');
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers  Artax\Mediator::notify
     */
    public function testNotifyUsesProviderForAdvancedLazyListenerLoad()
    {
        $m = new Mediator(new Provider(new ReflectionPool));
        $m->push('class_listener_event', 'MediatorTestNeedsDep',
            array('MediatorTestDependency')
        );
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
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
