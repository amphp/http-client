<?php

class BaseControllerTest extends BaseTest
{
  /**
   * @covers Artax\Controllers\BaseController::setDefaultAction
   * @covers Artax\Controllers\BaseController::getDefaultAction
   */
  public function testSetDefaultActionAssignsStringPropertyValue()
  {
    $c = $this->getMockForAbstractClass('Artax\Controllers\BaseController');
    
    $c->setDefaultAction('test');
    $this->assertEquals($c->getDefaultAction(), 'test');
    
    $c->setDefaultAction(array());
    $this->assertEquals($c->getDefaultAction(), 'Array');
    
    $c->setDefaultAction(123);
    $this->assertEquals($c->getDefaultAction(), '123');
  }
  
  /**
   * @covers \Artax\Controllers\BaseController::setDefaultAction
   */
  public function testSetDefaultActionReturnsObjectInstanceForMethodChaining()
  {
    $c = $this->getMockForAbstractClass('Artax\Controllers\BaseController');
    $obj = $c->setDefaultAction('test');
    $this->assertTrue($obj instanceof Artax\Controllers\BaseController);
  }
  
}

?>