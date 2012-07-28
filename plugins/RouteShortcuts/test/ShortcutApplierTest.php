<?php

use Artax\Routing\StdRoute,
    ArtaxPlugins\RouteShortcuts\ShortcutApplier;

class ShortcutApplierTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\RouteShortcuts\ShortcutApplier::__invoke
     */
    public function testMagicInvokeCallsTransformMethod() {
        $plugin = $this->getMock(
            'ArtaxPlugins\RouteShortcuts\ShortcutApplier',
            array('transform')
        );
        $plugin->expects($this->once())
               ->method('transform');
        $route = new StdRoute('/widgets', 'WidgetClass');
        $plugin($route);
    }
    
    /**
     * @covers ArtaxPlugins\RouteShortcuts\ShortcutApplier::transform
     */
    public function testTransformModifiesRouteUriPatterns() {
        $plugin = new ShortcutApplier();
        
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
