<?php
/**
 * ShortcutApplier Class File
 * 
 * @category    ArtaxPlugins
 * @package     RouteShortcuts
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace ArtaxPlugins\RouteShortcuts;

use Artax\Routing\Route;

/**
 * Applies shortcuts to Route URI patterns
 * 
 * @category    ArtaxPlugins
 * @package     RouteShortcuts
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ShortcutApplier {
    
    /**
     * @var array
     */
    private $replacements = array(
        '{<([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\|(.+)>}' => '(?P<$1>$2)',
        '{:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}' => '(?P<$1>[a-zA-Z0-9_\x7f-\xff.-]+)',
        '{#([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}' => '(?P<$1>\d+)',
    );
    
    /**
     * @param Route $route
     * @return void
     */
    public function __invoke(Route $route) {
        $this->transform($route);
    }
    
    /**
     * @param Route $route
     * @return void
     */
    public function transform(Route $route) {
        $pattern  = $route->getPattern();
        $needle   = array_keys($this->replacements);
        $haystack = array_values($this->replacements);
        $regex    = preg_replace($needle, $haystack, $pattern);
        
        $route->setPattern($regex);
    }
}
