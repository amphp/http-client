<?php

class MediatorTest extends PHPUnit_Framework_TestCase
{  
  public function testListenersIsEmptyOnInstantiation()
  {
    $m = new artax\Mediator;
    $this->assertEquals([], $m->all());
  }
  
  /**
   * @covers artax\Mediator::__construct
   * @covers artax\Mediator::all
   */
  public function testConstructorAddsPassedListeners()
  {
    $listeners = [
      ['test.event1', function() { return TRUE; }],
      ['test.event1', function() { return 42; }]
    ];
    
    $m = new artax\Mediator($listeners);
    
    $expected = [
      'test.event1' => [
        $listeners[0][1],
        $listeners[1][1]
      ]
    ];
    
    $this->assertEquals($expected, $m->all());
  }
  
  /**
   * @covers artax\Mediator::push
   * @covers artax\Mediator::last
   */
  public function testPushAddsEventListener()
  {
    $m = new artax\Mediator;
    $listeners = $m->push('test.event1', function() { return TRUE; });
    $this->assertEquals(1, $listeners);
    
    $listeners = $m->push('test.event1', function() { return 42; });
    $this->assertEquals(2, $listeners);
    $this->assertEquals(function() { return 42; }, $m->last('test.event1'));
    return $m;
  }
  
  /**
   * @covers artax\Mediator::unshift
   * @covers artax\Mediator::first
   */
  public function testUnshiftAddsEventListener()
  {
    $m = new artax\Mediator;
    $listeners = $m->push('test.event1', function() { return TRUE; });
    $this->assertEquals(1, $listeners);
    
    $listeners = $m->unshift('test.event1', function() { return 42; });
    $this->assertEquals(2, $listeners);
    $this->assertEquals(function() { return 42; }, $m->first('test.event1'));
    return $m;
  }
  
  /**
   * @covers artax\Mediator::first
   */
  public function testFirstReturnsNullIfNoListenersMatch()
  {
    $m = new artax\Mediator;
    $this->assertEquals(NULL, $m->first('test.event1'));
  }
  
  /**
   * @covers artax\Mediator::last
   */
  public function testLastReturnsNullIfNoListenersMatch()
  {
    $m = new artax\Mediator;
    $this->assertEquals(NULL, $m->last('test.event1'));
  }
  
  /**
   * @depends testPushAddsEventListener
   * @covers  artax\Mediator::count
   */
  public function testCountReturnsNumberOfListenersForSpecifiedEvent($m)
  {
    $this->assertEquals(2, $m->count('test.event1'));
  }
  
  /**
   * @depends testPushAddsEventListener
   * @covers  artax\Mediator::keys
   */
  public function testKeysReturnsArrayOfListenedForEvents($m)
  {
    $m->push('test.event2', function() { return 42; });
    $this->assertEquals(['test.event1', 'test.event2'], $m->keys());
    return $m;
  }
  
  /**
   * @depends testKeysReturnsArrayOfListenedForEvents
   * @covers  artax\Mediator::clear
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
   * @covers  artax\Mediator::pop
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
   * @covers  artax\Mediator::pop
   */
  public function testPopReturnsNullIfNoEventsMatchSpecifiedEvent($m)
  {
    $listener = $m->pop('test.eventDoesntExist');
    $this->assertEquals(NULL, $listener);
  }
  
  /**
   * @depends testKeysReturnsArrayOfListenedForEvents
   * @covers  artax\Mediator::shift
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
   * @covers  artax\Mediator::shift
   */
  public function testShiftReturnsNullIfNoEventsMatchSpecifiedEvent($m)
  {
    $listener = $m->shift('test.eventDoesntExist');
    $this->assertEquals(NULL, $listener);
  }
  
  /**
   * @covers  artax\Mediator::unshift
   */
  public function testUnshiftCreatesEventHolderIfNotExists()
  {
    $m = new artax\Mediator;
    $listeners = $m->push('test.event1', function() { return TRUE; });
    $this->assertEquals(1, $listeners);
    
    $listeners = $m->unshift('test.event2', function() { return 42; });
    $this->assertEquals(1, $listeners);
    $this->assertEquals(function() { return 42; }, $m->first('test.event2'));
  }
  
  /**
   * @covers  artax\Mediator::notify
   * @covers  artax\Mediator::all
   */
  public function testNotifyDistributesMessagesToListeners()
  {
    $m = new artax\Mediator;
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
   * @covers  artax\Mediator::all
   */
  public function testAllReturnsEventSpecificListIfSpecified()
  {
    $m = new artax\Mediator;
    $listener  = function() { return TRUE; };
    $listeners = $m->push('test.event1', $listener);
    
    $this->assertEquals([$listener], $m->all('test.event1'));
  }
}














