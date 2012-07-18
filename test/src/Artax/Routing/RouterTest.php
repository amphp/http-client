<?php

use Artax\Routing\Router,
    Artax\Routing\RoutePool,
    Artax\Routing\StdRouteFactory,
    Artax\Routing\MissingRoutesException;

class RouterTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Routing\Router::count
     */
    public function testCountReturnsCountFromRouteStorage() {
        $router = new Router;
        
        $this->assertEquals(0, count($router));
        $routePool = new RoutePool(new StdRouteFactory);
        $routePool->addRoute('/widgets', 'WidgetClass');
        $router->setRoutes($routePool);
        
        $this->assertEquals(1, count($router));
        
        return $router;
    }
    
    /**
     * @covers Artax\Routing\Router::setRoutes
     */
    public function testSetRoutesReturnsNull() {
        $router = new Router;
        $routePool = new RoutePool(new StdRouteFactory);
        $routePool->addRoute('/widgets', 'WidgetClass');
        $this->assertEquals(null, $router->setRoutes($routePool));
        $this->assertEquals(1, count($router));
    }
    
    /**
     * @covers Artax\Routing\Router::match
     */
    public function testMatchReturnsFalseOnMissingRoutes() {
        $router = new Router;
        $this->assertFalse($router->match('/widgets'));
        
        $routePool = new RoutePool(new StdRouteFactory);
        $router->setRoutes($routePool);
        
        $this->assertFalse($router->match('/widgets'));
    }
    
    /**
     * @covers Artax\Routing\Router::match
     */
    public function testMatchReturnsFalseOnMatchingFailure() {
        $routePool = new RoutePool(new StdRouteFactory);
        $routePool->addRoute('/kumqats', 'KumqatClass');
        
        $router = new Router;
        $router->setRoutes($routePool);
        
        $this->assertFalse($router->match('/widgets'));
    }
    
    /**
     * @covers Artax\Routing\Router::match
     * @covers Artax\Routing\Router::matchPatternAndBuildArgs
     */
    public function testMatchReturnsTrueAndNotifiesOnSuccessfulMatch() {
        $routes = new RoutePool(new StdRouteFactory);
        $routes->addRoute('/wontmatch', 'WontMatch');
        $routes->addRoute('/widgets/(?P<id>\d+)', 'WidgetClass');
        
        $router = new Router;
        $router->setRoutes($routes);
        
        $this->assertTrue($router->match('/widgets/42'));
        
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
