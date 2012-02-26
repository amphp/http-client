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
    $_SERVER['HTTP_HOST'] = 'artax';
    $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';
  }
  
  public function testBeginsEmpty()
  {
    $routes  = new artax\RouteList;
    $matcher = new artax\Matcher($routes);
    $this->assertEquals(NULL, $matcher->getController());
    $this->assertEquals([], $matcher->getArgs());
    return $matcher;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\Matcher::match
   */
  public function testMatchReturnsFalseWhenRouteListIsEmptyOrNoMatchExists($matcher)
  {
    $request = new artax\blocks\http\HttpRequest;
    $routes  = new artax\RouteList;
    $this->assertFalse($matcher->match($request, $routes));
  }
  
  /**
   * @covers artax\Matcher::match
   * @covers artax\Matcher::matchRoute
   */
  public function testMatchReturnsTrueOnRouteListMatch()
  {
    $_SERVER['REQUEST_URI'] = '/widgets';
    $request  = new artax\blocks\http\HttpRequest;
    $routeArr = [
      'route1' => ['/widgets',      'MatcherTestController.all'],
      'route2' => ['/widgets/<id>', 'MatcherTestController.show', ['id'=>'\d+']]
    ];
    $routes  = (new artax\RouteList)->addAllFromArr($routeArr);
    $matcher = new artax\Matcher;
    
    $this->assertTrue($matcher->match($request, $routes));
    $this->assertEquals('MatcherTestController.all', $matcher->getController());
    
    $_SERVER['REQUEST_URI'] = '/widgets/42';
    $request = new artax\blocks\http\HttpRequest;
    $this->assertTrue($matcher->match($request, $routes));
    $this->assertEquals('MatcherTestController.show', $matcher->getController());    
  }
}
