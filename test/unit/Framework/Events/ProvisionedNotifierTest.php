<?php

use Artax\Framework\Events\ProvisionedNotifier,
    Artax\Injection\Provider,
    Artax\Injection\ReflectionPool;

class ProvisionedNotifierTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidListeners() {
        return array(
            array(new StdClass),
            array(true),
            array(1),
            array(0),
            array(null)
        );
    }
    
    /**
     * @dataProvider provideInvalidListeners
     * @covers Artax\Framework\Events\ProvisionedNotifier::__construct
     * @covers Artax\Framework\Events\ProvisionedNotifier::isValidListener
     * @expectedException InvalidArgumentException
     */
    public function testIsValidListenerReturnsFalseOnInvalidListener($badListener) {
        $notifier = new ProvisionedNotifier(new Provider(new ReflectionPool));
        $notifier->push('test.event', $badListener);
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @covers Artax\Framework\Events\ProvisionedNotifier::getCallableListenerFromQueue
     */
    public function testNotifyDistributesMessagesToListeners() {
        $dp = new Provider(new ReflectionPool);
        $m = new ProvisionedNotifier($dp);
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
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @covers Artax\Framework\Events\ProvisionedNotifier::getCallableListenerFromQueue
     * @expectedException Artax\Framework\Events\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUnistantiableLazyListener() {
        $dp = new Provider(new ReflectionPool);
        $m = new ProvisionedNotifier($dp);
        
        $m->push('test.event1', 'UninstantiableClass');
        $m->notify('test.event1');
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @covers Artax\Framework\Events\ProvisionedNotifier::getCallableListenerFromQueue
     * @expectedException Artax\Framework\Events\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUncallableLazyListener() {
        $dp = new Provider(new ReflectionPool);
        $m = new ProvisionedNotifier($dp);
        
        $m->push('test.event1', 'ProvisionedNotifierTestUninvokableClass');
        $m->notify('test.event1');
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @covers Artax\Framework\Events\ProvisionedNotifier::getCallableListenerFromQueue
     */
    public function testNotifyUpdatesInvocationAndNotificationCounts() {
        $dp = new Provider(new ReflectionPool);
        $m = new ProvisionedNotifier($dp);
        $this->assertEquals(0, $m->notify('no.listeners.event'));
        
        $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $m->notify('test.event1'));
        
        $this->assertEquals(1, $m->getBroadcastCount('test.event1'));
        $this->assertEquals(1, $m->getInvocationCount('test.event1'));
        
        $m->push('test.event1', function() { return TRUE; });
        $m->push('test.event1', function() { return TRUE; });
        $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(4, $m->notify('test.event1'));
        
        $this->assertEquals(2, $m->getBroadcastCount('test.event1'));
        $this->assertEquals(5, $m->getInvocationCount('test.event1'));
        
        return $m;
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @covers Artax\Framework\Events\ProvisionedNotifier::getCallableListenerFromQueue
     */
    public function testNotifyUsesProviderForBasicLazyListenerLoad() {
        $m = new ProvisionedNotifier(new Provider(new ReflectionPool));        
        $m->push('class_listener_event', 'ProvisionedNotifierTestNeedsDep');
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @covers Artax\Framework\Events\ProvisionedNotifier::getCallableListenerFromQueue
     */
    public function testNotifyUsesProviderForAdvancedLazyListenerLoad() {
        $m = new ProvisionedNotifier(new Provider(new ReflectionPool));
        $m->push('class_listener_event', 'ProvisionedNotifierTestNeedsDep',
            array('ProvisionedNotifierTestDependency')
        );
        $this->expectOutputString('testVal');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @expectedException Artax\Framework\Events\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUninstantiableLazyListener() {
        
        $m = new ProvisionedNotifier(new Provider(new ReflectionPool));
        $m->push('class_listener_event', 'NonexistentClass');
        $m->notify('class_listener_event', 'testVal');
    }
    
    /**
     * @covers Artax\Framework\Events\ProvisionedNotifier::notify
     * @expectedException Artax\Framework\Events\BadListenerException
     */
    public function testNotifyThrowsExceptionOnUninvokableLazyListener() {
        
        $m = new ProvisionedNotifier(new Provider(new ReflectionPool));
        $m->push('class_listener_event', 'ProvisionedNotifierTestUninvokableClass');
        $m->notify('class_listener_event');
    }
}

class ProvisionedNotifierTestUninvokableClass {}

class ProvisionedNotifierTestDependency {
    public $testProp = 'testVal';
}

class ProvisionedNotifierTestNeedsDep {
    public $testDep;
    public function __construct(ProvisionedNotifierTestDependency $testDep) {
        $this->testDep = $testDep;
    }
    public function __invoke($arg1) {
        echo $arg1;
    }
}
