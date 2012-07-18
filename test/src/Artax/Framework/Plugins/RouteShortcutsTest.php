<?php

use Artax\Routing\StdRoute,
    Artax\Framework\Plugins\RouteShortcuts;

class RouteShortcutsTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Plugins\RouteShortcuts::__invoke
     */
    public function testMagicInvokeCallsTransformMethod() {
        $plugin = $this->getMock('Artax\\Framework\\Plugins\\RouteShortcuts', array('transform'));
        $plugin->expects($this->once())
               ->method('transform');
        $route = new StdRoute('/widgets', 'WidgetClass');
        $plugin($route);
    }
    
    /**
     * @covers Artax\Framework\Plugins\RouteShortcuts::transform
     */
    public function testTransformModifiesRouteUriPatterns() {
        $plugin = new RouteShortcuts();
        
        $route = new StdRoute('/widgets/#id', 'WidgetClass');
        $plugin($route);
        $this->assertEquals('/widgets/(?P<id>\d+)', $route->getPattern());
        
        $route = new StdRoute('/widgets/:type', 'WidgetClass');
        $plugin($route);
        $this->assertEquals('/widgets/(?P<type>[a-zA-Z0-9_\x7f-\xff.-]+)', $route->getPattern());
        
        $route = new StdRoute('/widgets/<myVar|[\d.,]+>', 'WidgetClass');
        $plugin($route);
        $this->assertEquals('/widgets/(?P<myVar>[\d.,]+)', $route->getPattern());
    }
}
