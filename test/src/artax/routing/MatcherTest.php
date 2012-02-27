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
    $routes  = new artax\routing\RouteList;
    $matcher = new artax\routing\Matcher($routes);
    $this->assertEquals(NULL, $matcher->getController());
    $this->assertEquals([], $matcher->getArgs());
    return $matcher;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers artax\routing\Matcher::match
   */
  public function testMatchReturnsFalseWhenRouteListIsEmptyOrNoMatchExists($matcher)
  {
    $request = new artax\blocks\http\HttpRequest;
    $routes  = new artax\routing\RouteList;
    $this->assertFalse($matcher->match($request, $routes));
  }
  
  /**
   * @covers artax\routing\Matcher::match
   * @covers artax\routing\Matcher::matchRoute
   */
  public function testMatchReturnsTrueOnRouteListMatch()
  {
    $_SERVER['REQUEST_URI'] = '/widgets';
    $request  = new artax\blocks\http\HttpRequest;
    $routeArr = [
      'route1' => ['/widgets',      'MatcherTestController.all'],
      'route2' => ['/widgets/<id>', 'MatcherTestController.show', ['id'=>'\d+']]
    ];
    $routes  = (new artax\routing\RouteList)->addAllFromArr($routeArr);
    $matcher = new artax\routing\Matcher;
    
    $this->assertTrue($matcher->match($request, $routes));
    $this->assertEquals('MatcherTestController.all', $matcher->getController());
    
    $_SERVER['REQUEST_URI'] = '/widgets/42';
    $request = new artax\blocks\http\HttpRequest;
    $this->assertTrue($matcher->match($request, $routes));
    $this->assertEquals('MatcherTestController.show', $matcher->getController());    
  }
}
