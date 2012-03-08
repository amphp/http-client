<?php

class RouteListTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers            Artax\Routing\RouteList::addAll
   * @expectedException InvalidArgumentException
   */
  public function testAddAllThrowsExceptionOnNonRouteListParameter()
  {
    $rl = new Artax\Routing\RouteList;
    $rl->addAll(new \SplObjectStorage);
  }
  
  /**
   * @covers            Artax\Routing\RouteList::attach
   * @expectedException InvalidArgumentException
   */
  public function testAttachThrowsExceptionOnNonRouteInterfaceParameter()
  {
    $rl = new Artax\Routing\RouteList;
    $rl->attach(new stdClass);
  }
  
  /**
   * @covers            Artax\Routing\RouteList::attach
   * @expectedException InvalidArgumentException
   * @exceptionMessage  attach() expects a string $data parameter:
   */
  public function testAttachThrowsExceptionOnNonStringDataParameter()
  {
    $rl = new Artax\Routing\RouteList;
    $route = new Artax\Routing\Route('controller_name', 'rt_controller/param1');
    $rl->attach($route, new stdClass);
  }
  
  /**
   * @covers Artax\Routing\RouteList::addAll
   */
  public function testAddAllAttachesRouteListParameter()
  {
    $rl1 = new Artax\Routing\RouteList;
    $rl2 = new Artax\Routing\RouteList;
    
    $route = new Artax\Routing\Route('controller_name', 'rt_controller/rt_action/param1');
    $rl2->attach($route);
    
    $rl1->addAll($rl2);
    
    $this->assertEquals($rl1, $rl2);
  }
  
  /**
   * @covers Artax\Routing\RouteList::attach
   */
  public function testAttachReturnsTrueOnAddOrFalseOnInvalidRouteParameter()
  {
    $rl = new Artax\Routing\RouteList;
    $route = new Artax\Routing\Route('/test', function() { return 1; });
    $rl->attach($route);
    
    $this->assertTrue($rl->contains($route));
  }
  
  /**
   * @covers            Artax\Routing\RouteList::addAllFromArr
   * @covers            Artax\Routing\RouteList::addFromArr
   * @covers            Artax\Routing\RouteList::find
   */
  public function testAddAllFromArrLoadsArrayRouteParameters()
  {
    $rl = new Artax\Routing\RouteList;
    
    $routeArr = [
      'route1' => ['widgets/<id>', 'Class',      ['id' => '\d+']],
      'route2' => ['widgets',      'Class.get',  ['_method' => 'GET']],
      'route3' => ['widgets',      'Class.post', ['_method' => 'POST']],
      'route4' => ['products',     'Class']
    ];
    
    $returnVal = $rl->addAllFromArr($routeArr);
    
    $route4 = $rl->find('route4');
    $this->assertEquals('products', $route4->getAlias());
    $this->assertEquals('Class', $route4->getController());
    $this->assertEquals($returnVal, $rl);
  }
  
  /**
   * @covers            Artax\Routing\RouteList::find
   */
  public function testFindReturnsFalseOnNonExistentDataKey()
  {
    $rl = new Artax\Routing\RouteList;    
    $route = $rl->find('routeTest');
    $this->assertFalse($route);
  }
}
