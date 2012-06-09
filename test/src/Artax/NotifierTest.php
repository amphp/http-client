<?php

use Artax\Notifier,
    Artax\Provider,
    Artax\ReflectionCacher;

class NotifierTest extends PHPUnit_Framework_TestCase
{
    public function testBeginsEmpty()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        return $m;
    }
    
    /**
     * @covers Artax\Notifier::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnUncallableListener()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $listeners = $m->push('test.event1', new StdClass);
    }
    
    /**
     * @covers Artax\Notifier::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnInvalidLazyDef()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $listeners = $m->push('test.event1', 'string', new StdClass);
    }
    
    /**
     * @covers Artax\Notifier::push
     * @covers Artax\Notifier::last
     */
    public function testPushAddsEventListenerAndReturnsCount()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        
        $this->assertEquals(1, $m->push('test_event', $f1));
        $this->assertEquals(2, $m->push('test_event', $f2));        
        $this->assertEquals($f1, $m->shift('test_event'));
        $this->assertEquals($f2, $m->shift('test_event'));

    }
  
    /**
     * @covers Artax\Notifier::pushAll
     * @expectedException InvalidArgumentException
     */
    public function testPushAllThrowsExceptionOnNonTraversableParameter()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->pushAll('not traversable');
    }
    
    /**
     * @covers Artax\Notifier::pushAll
     */
    public function testPushAllAddsNestedListenersFromTraversableParameter()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $cnt = $m->pushAll(array(
            'app.ready' => function(){},
            'app.event' => array(function(){}, function(){}, function(){}),
            'app.lazy'  => array('dot.notation'),
            'lazy.w.def'=> array('NotifierTestNeedsDep',
                array('testDep' => new NotifierTestDependency))
        ));
        $this->assertEquals(6, $cnt);
        $this->assertEquals(1, $m->count('app.ready'));
        $this->assertEquals(3, $m->count('app.event'));
        $this->assertEquals(1, $m->count('app.lazy'));
        $this->assertEquals(1, $m->count('lazy.w.def'));
    }
    
    /**
     * @covers Artax\Notifier::unshift
     * @expectedException InvalidArgumentException
     */
    public function testUnshiftThrowsExceptionOnInvalidLazyDef()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $listeners = $m->unshift('test.event1', 'string', 1);
    }
    
    /**
     * @covers Artax\Notifier::unshift
     * @covers Artax\Notifier::first
     */
    public function testUnshiftAddsEventListenerAndReturnsCount()
    {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $listeners = $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $listeners);
        
        $listeners = $m->unshift('test.event1', function() { return 42; });
        $this->assertEquals(2, $listeners);
        $this->assertEquals(function() { return 42; }, $m->first('test.event1'));
        return $m;
    }
    
    /**
     * @covers Artax\Notifier::unshift
     * @expectedException InvalidArgumentException
     */
    public function testUnshiftThrowsExceptionOnUncallableListener()
    {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $listeners = $m->unshift('test.event1', 1);
    }
    
    /**
     * @covers Artax\Notifier::first
     */
    public function testFirstReturnsNullIfNoListenersMatch()
    {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $this->assertEquals(NULL, $m->first('test.event1'));
    }
    
    /**
     * @covers Artax\Notifier::last
     */
    public function testLastReturnsNullIfNoListenersMatch()
    {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $this->assertEquals(NULL, $m->last('test.event1'));
    }
    
    /**
     * @covers  Artax\Notifier::count
     */
    public function testCountReturnsNumberOfListenersForSpecifiedEvent()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $m->push('test.event1', $f1);
        $m->push('test.event1', $f2);
        
        $this->assertEquals(2, $m->count('test.event1'));
    }
    
    /**
     * @covers  Artax\Notifier::keys
     */
    public function testKeysReturnsArrayOfListenedForEvents()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('test.event1', function() { return 42; });
        $m->push('test.event2', function() { return 42; });
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
        
        return $m;
    }
    
    /**
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Notifier::clear
     */
    public function testClearRemovesAllListenersAndListenedForEvents($m)
    {
        $m->clear('test.event2');
        $this->assertEquals(array('test.event1'), $m->keys());
        
        $m->clear();
        $this->assertEquals(array(), $m->keys());
    }
    
    /**
     * @covers  Artax\Notifier::pop
     */
    public function testPopRemovesLastListenerForSpecifiedEvent()
    {
        $m  = new Notifier(new Provider(new ReflectionCacher));
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
     * @covers  Artax\Notifier::pop
     */
    public function testPopReturnsNullIfNoEventsMatchSpecifiedEvent($m)
    {
        $listener = $m->pop('test.eventDoesntExist');
        $this->assertEquals(NULL, $listener);
    }
    
    /**
     * @covers  Artax\Notifier::shift
     */
    public function testShiftRemovesFirstListenerForSpecifiedEvent()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
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
     * @covers  Artax\Notifier::shift
     */
    public function testShiftReturnsNullIfNoEventsMatchSpecifiedEvent($m)
    {
        $listener = $m->shift('test.eventDoesntExist');
        $this->assertEquals(NULL, $listener);
    }
    
    /**
     * @covers  Artax\Notifier::unshift
     */
    public function testUnshiftCreatesEventQueueIfNotExists()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $this->assertEquals(1, $m->push('test.event1', $f1));
        $this->assertEquals(1, $m->unshift('test.event2', $f2));
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
    }
    
    /**
     * @covers  Artax\Notifier::notify
     */
    public function testNotifyDistributesMessagesToListeners()
    {
        $dp = new Provider(new ReflectionCacher);
        $m = new Notifier($dp);
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
     * @covers  Artax\Notifier::all
     */
    public function testAllReturnsEventSpecificListIfSpecified()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $f = function() { return TRUE; };
        $m->push('test.event1', $f);
        
        $this->assertEquals(array($f), $m->all('test.event1'));
    }
    
    /**
     * @covers  Artax\Notifier::notify
     */
    public function testNotifyUsesProviderForBasicLazyListenerLoad()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));        
        $m->push('class_listener_event', 'NotifierTestNeedsDep');
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers  Artax\Notifier::notify
     */
    public function testNotifyUsesProviderForAdvancedLazyListenerLoad()
    {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('class_listener_event', 'NotifierTestNeedsDep',
            array('NotifierTestDependency')
        );
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
}

class NotifierTestDependency
{
    public $testProp = 'testVal';
}

class NotifierTestNeedsDep
{
    public $testDep;
    public function __construct(NotifierTestDependency $testDep)
    {
        $this->testDep = $testDep;
    }
    
    public function __invoke($arg1)
    {
        echo $arg1;
    }
}
