<?php
/**
 * Router Class File
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Routing;

use StdClass,
    Traversable,
    InvalidArgumentException;

/**
 * Routes matchable patterns against a pool of registered routes
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class Router implements RouteMatcher {
    
    /**
     * @var string
     */
    private $matchedResource;
    
    /**
     * @var array
     */
    private $matchedArgs;
    
    /**
     * @param string $matchablePattern
     * @param RouteStorage $routeStorage
     * @return bool
     */
    public function match($matchablePattern, RouteStorage $routeStorage) {
        
        foreach ($routeStorage as $route) {
            $routePattern = '#^' . $route->getPattern() . '$#';
            if ($this->matchRouteArguments($routePattern, $matchablePattern)) {
                $this->matchedResource = $route->getResource();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * @param string $routePattern
     * @param string $matchablePattern
     * @return bool
     */
    private function matchRouteArguments($routePattern, $matchablePattern) {
        if (!preg_match($routePattern, $matchablePattern, $matchedArgs)) {
            return false;
        }
        
        foreach ($matchedArgs as $key => $val) {
            if ($key === (int) $key) {
                unset($matchedArgs[$key]);
            }
        }
        $this->matchedArgs = $matchedArgs;
        
        return true;
    }
    
    /**
     * @return string
     */
    public function getMatchedResource() {
        return $this->matchedResource;
    }
    
    /**
     * @return array
     */
    public function getMatchedArgs() {
        return $this->matchedArgs;
    }
}
