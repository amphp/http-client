<?php

class NotifierTraitTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\NotifierTrait::notify
   */
  public function testNotifySendsMessageToMediatorIfSet()
  {
    $stub = $this->getMock('MockMediatorBecausePHPUnitChokesOnCallableTypehint');
    $stub->expects($this->once())
         ->method('notify')
         ->will($this->returnArgument(0));
      
    $n = new NotifierTraitImplementationClass($stub);
    $this->assertEquals('test.event', $n->notify('test.event'));
  }
  
  /**
   * @covers artax\NotifierTrait::notify
   */
  public function testNotifySendsObjectInstanceOnNullDataParameter()
  {
    $stub = $this->getMock('MockMediatorBecausePHPUnitChokesOnCallableTypehint');
    $stub->expects($this->once())
         ->method('notify')
         ->will($this->returnArgument(1));
      
    $n = new NotifierTraitImplementationClass($stub);
    $this->assertEquals($n, $n->notify('test.event'));
  }
  
  /**
   * @covers artax\NotifierTrait::notify
   */
  public function testNotifyDoesNothingIfMediatorIsNull()
  {      
    $n = new NotifierTraitImplementationClass();
  }
}

class MockMediatorBecausePHPUnitChokesOnCallableTypehint
{
  public function notify($eventName, $data=NULL)
  {
  }
}

class NotifierTraitImplementationClass
{
  use artax\NotifierTrait;
  
  public function __construct($mediator=NULL)
  {
    $this->mediator = $mediator;
  }
}
