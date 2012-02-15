<?php

class CLIControllerTest extends BaseTest
{
  /**
   * @covers \artax\controllers\CLIController::__construct
   * @covers \artax\Controller::setRequest
   * @covers \artax\Controller::getRequest
   */
  public function testConstructorAssignsOptionPropertyIfSpecified()
  {
    $cfg = new artax\Config;
    $go = new artax\GetOptRequest;
    $c = $this->getMockForAbstractClass('Artax\Controllers\CLIController',
      array($cfg, $go)
    );
    $this->assertEquals($go, $c->getRequest());
    
    $c = $this->getMockForAbstractClass('Artax\Controllers\CLIController');
    $this->assertEquals(NULL, $c->getRequest());
  }
  
  /**
   * @covers \artax\controllers\CLIController::exec
   * @expectedException \artax\LogicException
   */
  public function testExecThrowsExceptionIfInvalidActionSpecified()
  {
    $cfg = new artax\Config;
    $go = new artax\GetOptRequest;
    $c = $this->getMockForAbstractClass('Artax\Controllers\CLIController',
      array($cfg, $go)
    );
    $c->exec();
  }
  
  /**
   * @covers \artax\controllers\CLIController::exec
   */
  public function testExecCallsSpecifiedControllerAction()
  {
    $cfg = new artax\Config;
    $go = new artax\GetOptRequest(NULL, array('testAction'));
    $c = new CLIControllerTest_WithActionMethod($cfg, $go);
    $c->exec();
    $this->assertTrue($c->called);
  }
  
}

/**
 * Supplies a valid action method for testing CLIController::exec
 */
class CLIControllerTest_WithActionMethod extends \artax\controllers\CLIController
{
  public $called = false;
  public function testAction() {
    $this->called = true;
  }
}


?>
