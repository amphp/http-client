<?php

use Artax\Notifier,
    Artax\Provider,
    Artax\ReflectionCacher;

class NotifierTest extends PHPUnit_Framework_TestCase {

    public function testBeginsEmpty() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        return $m;
    }
    
    /**
     * @covers Artax\Notifier::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnInvalidListener() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $listeners = $m->push('test.event1', new StdClass);
    }
    
    /**
     * @covers Artax\Notifier::push
     * @covers Artax\Notifier::last
     */
    public function testPushAddsEventListenerAndReturnsCount() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        
        $this->assertEquals(1, $m->push('test_event', $f1));
        $this->assertEquals(2, $m->push('test_event', $f2));
        $this->assertEquals($f1, $m->shift('test_event'));
        $this->assertEquals($f2, $m->shift('test_event'));
    }
    
    /**
     * @covers Artax\Notifier::push
     */
    public function testPushNotifiesListenersWhenInvoked() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        
        $f1 = function(){ return 1; };
        $m->push('test_event', $f1);
        $this->assertEquals(1, $m->countNotifications('artax.notifier.push'));
        
        $f2 = function(){ return 2; };
        $m->push('test_event', $f2);
        $this->assertEquals(2, $m->countNotifications('artax.notifier.push'));
    }
    
    /**
     * @covers Artax\Notifier::push
     */
    public function testPushRecursesOnIterableListenerParameter() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $m->push('test_event', array($f1, $f2));
        $this->assertEquals(2, $m->count('test_event'));
    }
  
    /**
     * @covers Artax\Notifier::pushAll
     * @expectedException InvalidArgumentException
     */
    public function testPushAllThrowsExceptionOnNonTraversableParameter() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->pushAll('not traversable');
    }
    
    /**
     * @covers Artax\Notifier::pushAll
     */
    public function testPushAllAddsNestedListenersFromTraversableParameter() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $cnt = $m->pushAll(array(
            'app.ready' => function(){},
            'app.event' => array(function(){}, function(){}, function(){}),
            'app.lazy'  => array('dot.notation')
        ));
        $this->assertNull($cnt);
        $this->assertEquals(1, $m->count('app.ready'));
        $this->assertEquals(3, $m->count('app.event'));
        $this->assertEquals(1, $m->count('app.lazy'));
    }
    
    /**
     * @covers Artax\Notifier::unshift
     * @covers Artax\Notifier::first
     */
    public function testUnshiftAddsEventListenerAndReturnsCount() {
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
    public function testUnshiftThrowsExceptionOnUncallableNonStringListener() {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $listeners = $m->unshift('test.event1', 1);
    }
    
    /**
     * @covers Artax\Notifier::unshift
     */
    public function testUnshiftNotifiesListenersWhenInvoked() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        
        $f1 = function(){ return 1; };
        $m->unshift('test_event', $f1);
        $this->assertEquals(1, $m->countNotifications('artax.notifier.unshift'));
        
        $f2 = function(){ return 2; };
        $m->unshift('test_event', $f2);
        $this->assertEquals(2, $m->countNotifications('artax.notifier.unshift'));
    }
    
    /**
     * @covers Artax\Notifier::first
     */
    public function testFirstReturnsNullIfNoListenersInQueueForSpecifiedEvent() {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $this->assertEquals(null, $m->first('test.event1'));
    }
    
    /**
     * @covers Artax\Notifier::last
     */
    public function testLastReturnsNullIfNoListenersInQueueForSpecifiedEvent() {
        $dp = new Provider(new ReflectionCacher);
        $m  = new Notifier($dp);
        $this->assertEquals(null, $m->last('test.event1'));
    }
    
    /**
     * @covers  Artax\Notifier::count
     */
    public function testCountReturnsNumberOfListenersForSpecifiedEvent() {
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
    public function testKeysReturnsArrayOfListenedForEvents() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('test.event1', function() { return 42; });
        $m->push('test.event2', function() { return 42; });
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
        
        return $m;
    }
    
    /**
     * @covers  Artax\Notifier::clear
     */
    public function testClearRemovesAllListenersForSpecifiedEvent() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('test.event1', function() { return 42; });
        $m->push('test.event2', function() { return 42; });
        
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
        $m->clear('test.event2');
        $this->assertEquals(array('test.event1'), $m->keys());
    }
    
    /**
     * @covers  Artax\Notifier::pop
     */
    public function testPopRemovesLastListenerForSpecifiedEvent() {
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
    public function testPopReturnsNullIfNoEventsMatchSpecifiedEvent($m) {
        $listener = $m->pop('test.eventDoesntExist');
        $this->assertEquals(null, $listener);
    }
    
    /**
     * @covers  Artax\Notifier::shift
     */
    public function testShiftRemovesFirstListenerForSpecifiedEvent() {
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
    public function testShiftReturnsNullIfNoEventsMatchSpecifiedEvent($m) {
        $listener = $m->shift('test.eventDoesntExist');
        $this->assertEquals(null, $listener);
    }
    
    /**
     * @covers Artax\Notifier::unshift
     */
    public function testUnshiftCreatesEventQueueIfNotExists() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $f1 = function(){ return 1; };
        $f2 = function(){ return 2; };
        $this->assertEquals(1, $m->push('test.event1', $f1));
        $this->assertEquals(1, $m->unshift('test.event2', $f2));
        $this->assertEquals(array('test.event1', 'test.event2'), $m->keys());
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @covers Artax\Notifier::incrementEventBroadcastCount
     * @covers Artax\Notifier::incrementListenerInvocationCount
     * @covers Artax\Notifier::getCallableListenerFromQueue
     */
    public function testNotifyDistributesMessagesToListeners() {
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
     * @covers Artax\Notifier::notify
     * @covers Artax\Notifier::getCallableListenerFromQueue
     * @expectedException Artax\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUnistantiableLazyListener() {
        $dp = new Provider(new ReflectionCacher);
        $m = new Notifier($dp);
        
        $m->push('test.event1', 'UninstantiableClass');
        $m->notify('test.event1');
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @covers Artax\Notifier::getCallableListenerFromQueue
     * @expectedException Artax\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUncallableLazyListener() {
        $dp = new Provider(new ReflectionCacher);
        $m = new Notifier($dp);
        
        $m->push('test.event1', 'NotifierTestUninvokableClass');
        $m->notify('test.event1');
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @covers Artax\Notifier::countNotifications
     * @covers Artax\Notifier::countInvocations
     * @covers Artax\Notifier::incrementEventBroadcastCount
     * @covers Artax\Notifier::incrementListenerInvocationCount
     * @covers Artax\Notifier::getCallableListenerFromQueue
     */
    public function testNotifyUpdatesInvocationAndNotificationCounts() {
        $dp = new Provider(new ReflectionCacher);
        $m = new Notifier($dp);
        $this->assertEquals(0, $m->notify('no.listeners.event'));
        
        $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $m->notify('test.event1'));
        
        $this->assertEquals(1, $m->countNotifications('test.event1'));
        $this->assertEquals(1, $m->countInvocations('test.event1'));
        
        $m->push('test.event1', function() { return TRUE; });
        $m->push('test.event1', function() { return TRUE; });
        $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(4, $m->notify('test.event1'));
        
        $this->assertEquals(2, $m->countNotifications('test.event1'));
        $this->assertEquals(5, $m->countInvocations('test.event1'));
        
        return $m;
    }
    
    /**
     * @covers Artax\Notifier::countNotifications
     */
    public function testCountNotificationsReturnsAggregateCountOnNullEventParam() {
        $dp = new Provider(new ReflectionCacher);
        $m = new Notifier($dp);
        
        $this->assertEquals(0, $m->notify('test.event1'));
        $this->assertEquals(0, $m->notify('test.event2'));
        $this->assertEquals(0, $m->notify('test.event3'));
        $this->assertEquals(0, $m->countNotifications('nonexistent.event'));
    }
    
    /**
     * @covers Artax\Notifier::countInvocations
     */
    public function testCountInvocationsReturnsAggregateCountOnNullEventParam() {
        $dp = new Provider(new ReflectionCacher);
        $m = new Notifier($dp);
        
        $m->push('test.event1', function() { return TRUE; });
        $m->push('test.event1', function() { return TRUE; });
        $m->push('test.event2', function() { return TRUE; });
        
        $this->assertEquals(2, $m->notify('test.event1'));
        $this->assertEquals(1, $m->notify('test.event2'));
        $this->assertEquals(2, $m->notify('test.event1'));
        $this->assertEquals(0, $m->notify('test.event3'));
        $this->assertEquals(0, $m->countInvocations('nonexistent.event'));
    }
    
    /**
     * @covers Artax\Notifier::all
     */
    public function testAllReturnsEventSpecificListIfSpecified() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $f = function() { return TRUE; };
        $m->push('test.event1', $f);
        
        $this->assertEquals(array($f), $m->all('test.event1'));
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @covers Artax\Notifier::incrementEventBroadcastCount
     * @covers Artax\Notifier::incrementListenerInvocationCount
     * @covers Artax\Notifier::getCallableListenerFromQueue
     */
    public function testNotifyUsesProviderForBasicLazyListenerLoad() {
        $m = new Notifier(new Provider(new ReflectionCacher));        
        $m->push('class_listener_event', 'NotifierTestNeedsDep');
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @covers Artax\Notifier::incrementEventBroadcastCount
     * @covers Artax\Notifier::incrementListenerInvocationCount
     * @covers Artax\Notifier::getCallableListenerFromQueue
     */
    public function testNotifyUsesProviderForAdvancedLazyListenerLoad() {
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('class_listener_event', 'NotifierTestNeedsDep',
            array('NotifierTestDependency')
        );
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @expectedException Artax\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUninstantiableLazyListener() {
        
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('class_listener_event', 'NonexistentClass');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers Artax\Notifier::notify
     * @expectedException Artax\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUninvokableLazyListener() {
        
        $m = new Notifier(new Provider(new ReflectionCacher));
        $m->push('class_listener_event', 'NotifierTestUninvokableClass');
        $m->notify('class_listener_event');
    }
}

class NotifierTestUninvokableClass {}

class NotifierTestDependency {
    public $testProp = 'testVal';
}

class NotifierTestNeedsDep {
    public $testDep;
    public function __construct(NotifierTestDependency $testDep) {
        $this->testDep = $testDep;
    }
    public function __invoke($arg1) {
        echo $arg1;
    }
}
