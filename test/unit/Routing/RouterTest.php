<?php

use Artax\Routing\Router,
    Artax\Routing\RoutePool,
    Artax\Routing\StdRouteFactory,
    Artax\Routing\MissingRoutesException;

class RouterTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Routing\Router::match
     */
    public function testMatchReturnsFalseOnMatchingFailure() {
        $routePool = new RoutePool(new StdRouteFactory);
        $routePool->addRoute('/kumqats', 'KumqatClass');
        
        $router = new Router;
        $this->assertFalse($router->match('/widgets', $routePool));
    }
    
    /**
     * @covers Artax\Routing\Router::match
     * @covers Artax\Routing\Router::matchRouteArguments
     */
    public function testMatchReturnsTrueAndNotifiesOnSuccessfulMatch() {
        $routes = new RoutePool(new StdRouteFactory);
        $routes->addRoute('/wontmatch', 'WontMatch');
        $routes->addRoute('/widgets/(?P<id>\d+)', 'WidgetClass');
        
        $router = new Router;
        $this->assertTrue($router->match('/widgets/42', $routes));
        
        return $router;
    }
    
    /**
     * @depends testMatchReturnsTrueAndNotifiesOnSuccessfulMatch
     * @covers Artax\Routing\Router::getMatchedResource
     */
    public function testGetMatchedResourceReturnsResourceClassNameAfterMatch($router) {
        $this->assertEquals('WidgetClass', $router->getMatchedResource());
    }
    
    /**
     * @depends testMatchReturnsTrueAndNotifiesOnSuccessfulMatch
     * @covers Artax\Routing\Router::getMatchedArgs
     */
    public function testGetMatchedArgsReturnsRouteArgsAfterMatch($router) {
        $this->assertEquals(array('id'=>42), $router->getMatchedArgs());
    }
}

class WidgetClass {
    public function get($arg = 42) {
        return $arg + 1;
    }
}
