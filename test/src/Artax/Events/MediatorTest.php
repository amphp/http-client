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
      $m = new Artax\Events\Mediator;
      $listeners = $m->push('test.event1', 'this_is_not_callable');
  }
  
  /**
   * @covers Artax\Events\Mediator::push
   * @covers Artax\Events\Mediator::last
   */
  public function testPushAddsEventListenerAndReturnsCount()
  {
      $m = new MediatorTestImplementationClass;
      $m->push('test_event', [function(){}, 'key'=>function(){}, function(){}]);
      $this->assertEquals(3, $m->count('test_event'));
      
      $m = new Artax\Events\Mediator;
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
  public function testPushAddsMultipleListenersOnTraversableListenerParameter()
  {
      $m = new MediatorTestImplementationClass;
      $traversable = new ArrayObject;
      $traversable->append(function(){});
      $traversable->append(function(){});
      $m->push('test_event', $traversable);
      $this->assertEquals(2, $m->count('test_event'));
      
      $m->push('test_event', [function(){}, 'key'=>function(){}]);
      $this->assertEquals(4, $m->count('test_event'));
      
      $scTraversable = new StdClass;
      $scTraversable->test_event = [function(){}, function(){}];
      $m->push('test_event', $scTraversable);
      $this->assertEquals(6, $m->count('test_event'));
      
      
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
      $m = new Artax\Events\Mediator;
      $listeners = $m->push('test.event1', function() { return TRUE; });
      $this->assertEquals(1, $listeners);
      
      $listeners = $m->unshift('test.event1', function() { return 42; });
      $this->assertEquals(2, $listeners);
      $this->assertEquals(function() { return 42; }, $m->first('test.event1'));
      return $m;
  }
  
  /**
   * @covers Artax\Events\Mediator::first
   */
  public function testFirstReturnsNullIfNoListenersMatch()
  {
      $m = new Artax\Events\Mediator;
      $this->assertEquals(NULL, $m->first('test.event1'));
  }
  
  /**
   * @covers Artax\Events\Mediator::last
   */
  public function testLastReturnsNullIfNoListenersMatch()
  {
      $m = new Artax\Events\Mediator;
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
      $m = new Artax\Events\Mediator;
      $listeners = $m->push('test.event1', function() { return TRUE; });
      $this->assertEquals(1, $listeners);
      
      $listeners = $m->unshift('test.event2', function() { return 42; });
      $this->assertEquals(1, $listeners);
      $this->assertEquals(function() { return 42; }, $m->first('test.event2'));
  }
  
  /**
   * @covers  Artax\Events\Mediator::notify
   * @covers  Artax\Events\Mediator::all
   */
  public function testNotifyDistributesMessagesToListeners()
  {
      $m = new Artax\Events\Mediator;
      $this->assertEquals(0, $m->notify('no.listeners.event'));
      
      $listeners = $m->push('test.event1', function() { return TRUE; });
      $this->assertEquals(1, $m->notify('test.event1'));
      
      
      $listeners = $m->unshift('test.event2', function($x) {
          return isset($x) ? 42*$x : 42;
      });
      
      $m->push('test.event2', function() { return FALSE; });
      $m->push('test.event2', function() { return TRUE; });
      $this->assertEquals(2, $m->notify('test.event2'));
  }
  
  /**
   * @covers  Artax\Events\Mediator::all
   */
  public function testAllReturnsEventSpecificListIfSpecified()
  {
      $m = new Artax\Events\Mediator;
      $listener  = function() { return TRUE; };
      $listeners = $m->push('test.event1', $listener);    
      $this->assertEquals([$listener], $m->all('test.event1'));
  }
}

class MediatorTestImplementationClass extends Artax\Events\Mediator
{
  use MagicTestGetTrait;
}











