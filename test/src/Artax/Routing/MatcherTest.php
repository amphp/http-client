<?php

class MatcherTest extends PHPUnit_Framework_TestCase
{
  /**
   * These $_SERVER values are required to avoid an HttpException when
   * instantiating an HttpRequest object.
   */
  public function setUp()
  {
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'Artax';
    $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';
  }
  
  public function testBeginsEmpty()
  {
    $request = new Artax\Http\HttpRequest;
    $routes  = new Artax\Routing\RouteList;
    $matcher = new Artax\Routing\Matcher($request, $routes);
    
    $this->assertEquals(NULL, $matcher->getController());
    $this->assertEquals([], $matcher->getArgs());
    return $matcher;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers Artax\Routing\Matcher::match
   */
  public function testMatchReturnsFalseWhenRouteListIsEmptyOrNoMatchExists($matcher)
  {
    $this->assertFalse($matcher->match());
  }
  
  /**
   * @covers Artax\Routing\Matcher::match
   * @covers Artax\Routing\Matcher::matchRoute
   */
  public function testMatchReturnsTrueOnRouteListMatch()
  {
    $_SERVER['REQUEST_URI'] = '/widgets';
    
    $routeArr = [
      'route1' => ['/widgets',      'MatcherTestController.all'],
      'route2' => ['/widgets/<id>', 'MatcherTestController.show', ['id'=>'\d+']]
    ];
    $routes  = (new Artax\Routing\RouteList)->addAllFromArr($routeArr);
    $request  = new Artax\Http\HttpRequest;
    $matcher = new Artax\Routing\Matcher($request, $routes);
    
    $this->assertTrue($matcher->match());
    $this->assertEquals('MatcherTestController.all', $matcher->getController());
    
    $_SERVER['REQUEST_URI'] = '/widgets/42';
    
    $request = new Artax\Http\HttpRequest;
    $matcher = new Artax\Routing\Matcher($request, $routes);
    
    $this->assertTrue($matcher->match());
    $this->assertEquals('MatcherTestController.show', $matcher->getController());    
  }
}
