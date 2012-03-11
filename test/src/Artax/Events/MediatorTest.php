<?php

class MediatorTest extends PHPUnit_Framework_TestCase
{
    public function testBeginsEmpty()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $this->assertEquals([], $m->listeners);
        return $m;
    }
    
    /**
     * @covers Artax\Events\Mediator::push
     * @expectedException InvalidArgumentException
     */
    public function testPushThrowsExceptionOnUncallableListener()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $listeners = $m->push('test.event1', 1);
    }
    
    /**
     * @covers Artax\Events\Mediator::push
     * @covers Artax\Events\Mediator::last
     */
    public function testPushAddsEventListenerAndReturnsCount()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $m->push('test_event', [function(){}, 'key'=>function(){}, function(){}]);
        $this->assertEquals(3, $m->count('test_event'));
        
        $m = new MediatorTestImplementationClass($dp);
        $listeners = $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $listeners);
        
        $listeners = $m->push('test.event1', function() { return 42; });
        $this->assertEquals(2, $listeners);
        $this->assertEquals(function() { return 42; }, $m->last('test.event1'));
        
        return $m;
    }
  
    /**
     * @covers Artax\Events\Mediator::push
     */
    public function testPushAddsMultipleListenersOnTraversableParameter()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $traversable = new ArrayObject;
        $nested = clone $traversable;
        $nested->append(function(){});
        $nested->append(function(){});
        $traversable->append($nested);
        
        $m->push('test_event', $traversable);
        $this->assertEquals(2, $m->count('test_event'));
        
        $m->push('test_event', [function(){}, 'key'=>function(){}]);
        $this->assertEquals(4, $m->count('test_event'));
        
        $scTraversable = new StdClass;
        $scTraversable->test_event = [function(){}, function(){}];
        $m->push('test_event', $scTraversable);
        $this->assertEquals(6, $m->count('test_event'));
        
        $m->push('test_event', [
            function(){},
            'key'=>function(){},
            ['lazy.class1', 'lazy.class2'=>'lazy.class2']
        ]);
        $this->assertEquals(10, $m->count('test_event'));
    }
  
    /**
     * @covers Artax\Events\Mediator::pushAll
     * @expectedException InvalidArgumentException
     */
    public function testPushAllThrowsExceptionOnNonTraversableParameter()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $m->pushAll('not traversable');
    }
    
    /**
     * @covers Artax\Events\Mediator::pushAll
     */
    public function testPushAllAddsNestedListenersFromTraversableParameter()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $cnt = $m->pushAll([
            'app.ready'=>function(){},
             'app.anything'=>[function(){}, function(){}, function(){}]
        ]);
        $this->assertEquals(4, $cnt);
        $this->assertEquals(1, $m->count('app.ready'));
        $this->assertEquals(3, $m->count('app.anything'));
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
     * @depends testPushAddsEventListenerAndReturnsCount
     * @covers  Artax\Events\Mediator::count
     */
    public function testCountReturnsNumberOfListenersForSpecifiedEvent($m)
    {
        $this->assertEquals(2, $m->count('test.event1'));
    }
    
    /**
     * @depends testPushAddsEventListenerAndReturnsCount
     * @covers  Artax\Events\Mediator::keys
     */
    public function testKeysReturnsArrayOfListenedForEvents($m)
    {
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
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Events\Mediator::pop
     */
    public function testPopRemovesLastListenerForSpecifiedEvent($m)
    {
        $count = $m->count('test.event1');
        $f = function() { return 'unnecessary'; };
        $m->push('test.event1', $f);
        $listener = $m->pop('test.event1');
        $this->assertEquals($f, $listener);
        $this->assertEquals($count, $m->count('test.event1'));
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
     * @depends testKeysReturnsArrayOfListenedForEvents
     * @covers  Artax\Events\Mediator::shift
     */
    public function testShiftRemovesFirstListenerForSpecifiedEvent($m)
    {
        $count = $m->count('test.event1');
        $f = function() { return 'unnecessary'; };
        $m->push('test.event1', $f);
        $listener = $m->shift('test.event1');
        $this->assertEquals($f, $listener);
        $this->assertEquals($count, $m->count('test.event1'));
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
    public function testUnshiftCreatesEventHolderIfNotExists()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $listeners = $m->push('test.event1', function() { return TRUE; });
        $this->assertEquals(1, $listeners);
        
        $listeners = $m->unshift('test.event2', function() { return 42; });
        $this->assertEquals(1, $listeners);
        $this->assertEquals(function() { return 42; }, $m->first('test.event2'));
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
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        $listener  = function() { return TRUE; };
        $listeners = $m->push('test.event1', $listener);    
        $this->assertEquals([$listener], $m->all('test.event1'));
    }
    
    /**
     * @covers  Artax\Events\Mediator::notify
     */
    public function testNotifyUsesProviderForLazyListenerLoad()
    {
        $dp = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $m = new MediatorTestImplementationClass($dp);
        
        $m->push('class_listener_event', 'MediatorTestNeedsDep');
        
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










