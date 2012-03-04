<?php

class RouteListTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers            artax\routing\RouteList::addAll
   * @expectedException InvalidArgumentException
   */
  public function testAddAllThrowsExceptionOnNonRouteListParameter()
  {
    $rl = new artax\routing\RouteList;
    $rl->addAll(new \SplObjectStorage);
  }
  
  /**
   * @covers            artax\routing\RouteList::attach
   * @expectedException InvalidArgumentException
   */
  public function testAttachThrowsExceptionOnNonRouteInterfaceParameter()
  {
    $rl = new artax\routing\RouteList;
    $rl->attach(new stdClass);
  }
  
  /**
   * @covers            artax\routing\RouteList::attach
   * @expectedException InvalidArgumentException
   * @exceptionMessage  attach() expects a string $data parameter:
   */
  public function testAttachThrowsExceptionOnNonStringDataParameter()
  {
    $rl = new artax\routing\RouteList;
    $route = new artax\routing\Route('controller_name', 'rt_controller/param1');
    $rl->attach($route, new stdClass);
  }
  
  /**
   * @covers artax\routing\RouteList::addAll
   */
  public function testAddAllAttachesRouteListParameter()
  {
    $rl1 = new artax\routing\RouteList;
    $rl2 = new artax\routing\RouteList;
    
    $route = new artax\routing\Route('controller_name', 'rt_controller/rt_action/param1');
    $rl2->attach($route);
    
    $rl1->addAll($rl2);
    
    $this->assertEquals($rl1, $rl2);
  }
  
  /**
   * @covers artax\routing\RouteList::attach
   */
  public function testAttachReturnsTrueOnAddOrFalseOnInvalidRouteParameter()
  {
    $rl = new artax\routing\RouteList;
    $route = new artax\routing\Route('/test', function() { return 1; });
    $rl->attach($route);
    
    $this->assertTrue($rl->contains($route));
  }
  
  /**
   * @covers            artax\routing\RouteList::addAllFromArr
   * @covers            artax\routing\RouteList::addFromArr
   * @covers            artax\routing\RouteList::find
   */
  public function testAddAllFromArrLoadsArrayRouteParameters()
  {
    $rl = new artax\routing\RouteList;
    
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
   * @covers            artax\routing\RouteList::find
   */
  public function testFindReturnsFalseOnNonExistentDataKey()
  {
    $rl = new artax\routing\RouteList;    
    $route = $rl->find('routeTest');
    $this->assertFalse($route);
  }
}
