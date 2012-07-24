<?php

use Artax\Routing\StdRoute;

class StdRouteTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Routing\StdRoute::__construct
     */
    public function testConstructorAssignsPropertiesOnCreation() {
        $route = new StdRoute('widgets', 'WidgetResource');
        $this->assertEquals('/widgets', $route->getPattern());
        $this->assertEquals('WidgetResource', $route->getResource());
    }
    
    /**
     * @covers Artax\Routing\StdRoute::setResource
     */
    public function testSetResourceAssignsProperty() {
        $route = new StdRoute('widgets', 'WidgetResource');
        $this->assertEquals('WidgetResource', $route->getResource());
        $this->assertEquals(null, $route->setResource('OtherController'));
        $this->assertEquals('OtherController', $route->getResource());
        
        $route->setResource('WidgetResource');
    }
    
    /**
     * @covers Artax\Routing\StdRoute::setPattern
     */
    public function testSetPatternAssignsProperty() {
        $route = new StdRoute('widgets', 'WidgetResource');
        $this->assertEquals('/widgets', $route->getPattern());
        $this->assertEquals(null, $route->setPattern('/widgets-transformed'));
        $this->assertEquals('/widgets-transformed', $route->getPattern());
    }
    
    /**
     * @covers Artax\Routing\StdRoute::getPattern
     */
    public function testGetPatternReturnsPropertyValue() {
        $route = new StdRoute('widgets', 'WidgetResource');
        $this->assertEquals('/widgets', $route->getPattern());
    }
    
    /**
     * @covers Artax\Routing\StdRoute::getResource
     */
    public function testGetControllerReturnsPropertyValue() {
        $route = new StdRoute('widgets', 'WidgetResource');
        $this->assertEquals('WidgetResource', $route->getResource());
    }
    
    /**
     * @covers Artax\Routing\StdRoute::serialize
     * @covers Artax\Routing\StdRoute::unserialize
     */
    public function testSerializationProtocols() {
        $route = new StdRoute('/widgets', 'WidgetResource');
        $this->assertEquals('/widgets', $route->getPattern());
        $this->assertEquals('WidgetResource', $route->getResource());
        
        $dup = unserialize(serialize($route));
        
        $this->assertEquals('/widgets', $dup->getPattern());
        $this->assertEquals('WidgetResource', $dup->getResource());
    }
}
