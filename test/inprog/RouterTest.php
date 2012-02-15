<?php

class RouterTest extends BaseTest
{
  /**
   * @covers Artax\Router::__construct
   * @covers Artax\Router::getRoutes
   * @covers Artax\Router::setRoutes
   * @covers Artax\Router::getRequest
   * @covers Artax\Router::setRequest
   */
  public function testConstructorInitializesPassedProperties()
  {
    $r = new Artax\Router();
    $correct = $r->getRoutes() instanceof Artax\RouteList;
    $this->assertTrue($correct);
    
    $routes = new Artax\RouteList;
    $route = new Artax\Route('controller_name', 'rt_controller/rt_action/param1');
    $routes->attach($route);
    $r = new Artax\Router();
    $r->setRoutes($routes);
    $this->assertEquals($r->getRoutes(), $routes);
    
    return $r;
  }
  
  /**
   * @covers Artax\Router::routeRequest
   */
  public function testRouteRequestMatchesExistingRoute()
  {
    $_SERVER['REQUEST_URI'] = 'controller_name';
    
    // Build Config
    $cfg = new Artax\Config();
    
    // Build Request
    $req = new Artax\WebRequest();
    
    // Build Router and add Route
    $r     = new Artax\Router($cfg);
    $rl    = new Artax\RouteList();
    $route = new Artax\Route('controller_name', 'rt_controller/rt_action/param1');
    
    $rl->attach($route);
    
    $r = $r->setRoutes($rl)->setRequest($req)->routeRequest($req);
    
    // Check that the router correctly applied the route rules instead of the
    // default uri
    $this->assertEquals($r->cntrl_name, 'RtController');
    $this->assertEquals($r->cntrl_action, 'rtAction');
  }
  
  /**
   * @covers            Artax\Router::routeRequest
   * @expectedException Artax\LogicException
   */
  public function testRouteRequestThrowsExceptionOnMissingRequest()
  {
    $r = new Artax\Router();
    $r->routeRequest();
  }
  
  /**
   * @covers \Artax\Router::studlyCaps
   */
  public function testStudlyCapsAppliesStudlyCapsToClassesAndCamelCaseToMethods()
  {
    $uri = 'controller';
    $uri = Artax\Router::studlyCaps($uri);
    $this->assertEquals($uri, 'Controller');
    
    $uri = 'controller_name';
    $uri = Artax\Router::studlyCaps($uri);
    $this->assertEquals($uri, 'ControllerName');
    
    $uri = 'method_name';
    $uri = Artax\Router::studlyCaps($uri, TRUE);
    $this->assertEquals($uri, 'methodName');
    
    $uri = 'method';
    $uri = Artax\Router::studlyCaps($uri, TRUE);
    $this->assertEquals($uri, 'method');
  }
  
  /**
   * @covers Artax\Router::__get
   * @expectedException Artax\OutOfBoundsException
   */
  public function test__getThrowsExceptionOnInvalidProperty()
  {
    $r = new Artax\Router();
    $var = $r->invalid_property_name;
  }
  
  /**
   * @covers Artax\Router::__get
   */
  public function test__getReturnsPropertyValue()
  {  
    $cfg = new Artax\Config();
    $r = new Artax\Router($cfg);
    $this->assertEquals($cfg, $r->cfg);
  }
  
  /**
   * @covers Artax\Router::getRequest
   */
  public function testGetRequestReturnsPropertyValue()
  {  
    $_SERVER['REQUEST_URI'] = 'test/page';
    $req = new Artax\WebRequest();
    $r = new artax\Router;
    $r->setRequest($req);
    $this->assertEquals($req, $r->getRequest());    
  }
}

?>
