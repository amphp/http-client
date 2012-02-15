<?php

class RouteListTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers            artax\RouteList::addAll
   * @expectedException artax\exceptions\InvalidArgumentException
   */
  public function testAddAllThrowsExceptionOnNonRouteListParameter()
  {
    $rl = new artax\RouteList;
    $rl->addAll(new \SplObjectStorage);
  }
  
  /**
   * @covers            artax\RouteList::attach
   * @expectedException artax\exceptions\InvalidArgumentException
   */
  public function testAttachThrowsExceptionOnNonRouteInterfaceParameter()
  {
    $rl = new artax\RouteList;
    $rl->attach(new stdClass);
  }
  
  /**
   * @covers            artax\RouteList::attach
   * @expectedException artax\exceptions\InvalidArgumentException
   * @exceptionMessage  attach() expects a string $data parameter:
   */
  public function testAttachThrowsExceptionOnNonStringDataParameter()
  {
    $rl = new artax\RouteList;
    $route = new artax\Route('controller_name', 'rt_controller/param1');
    $rl->attach($route, new stdClass);
  }
  
  /**
   * @covers artax\RouteList::addAll
   */
  public function testAddAllAttachesRouteListParameter()
  {
    $rl1 = new artax\RouteList;
    $rl2 = new artax\RouteList;
    
    $route = new artax\Route('controller_name', 'rt_controller/rt_action/param1');
    $rl2->attach($route);
    
    $rl1->addAll($rl2);
    
    $this->assertEquals($rl1, $rl2);
  }
  
  /**
   * @covers artax\RouteList::attach
   */
  public function testAttachReturnsTrueOnAddOrFalseOnInvalidRouteParameter()
  {
    $rl = new artax\RouteList;
    $route = new artax\Route('/test', function() { return 1; });
    $rl->attach($route);
    
    $this->assertTrue($rl->contains($route));
  }
  
  /**
   * @covers            artax\RouteList::addAllFromArr
   * @covers            artax\RouteList::addFromArr
   * @covers            artax\RouteList::find
   */
  public function testAddAllFromArrLoadsArrayRouteParameters()
  {
    $rl = new artax\RouteList;
    
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
   * @covers            artax\RouteList::find
   */
  public function testFindReturnsFalseOnNonExistentDataKey()
  {
    $rl = new artax\RouteList;    
    $route = $rl->find('routeTest');
    $this->assertFalse($route);
  }
}




















