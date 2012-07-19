<?php
/**
 * RoutePool Class File
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
    SplObjectStorage,
    InvalidArgumentException;

/**
 * An iterable, serializable pool of Route instances
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class RoutePool implements RouteStorage {

    /**
     * @var SplObjectStorage
     */
    private $routes;
    
    /**
     * @var RouteFactory
     */
    private $routeFactory;
    
    /**
     * @param RouteFactory $routeFactory
     * @return void
     */
    public function __construct(RouteFactory $routeFactory) {
        $this->routes = new SplObjectStorage;
        $this->routeFactory = $routeFactory;
    }
    
    /**
     * @param string $matchablePattern
     * @param mixed $resource
     * @return void
     */
    public function addRoute($matchablePattern, $resource) {
        $route = $this->routeFactory->make($matchablePattern, $resource);
        $this->routes->attach($route);
    }
    
    /**
     * @param mixed $iterable An array, StdClass or Traversable route list
     * @return void
     * @throws InvalidArgumentException
     */
    public function addAllRoutes($iterable) {
        if (!($iterable instanceof Traversable
            || $iterable instanceof StdClass
            || is_array($iterable)
        )) {
            throw new InvalidArgumentException(
                'Argument 1 passed to '.get_class($this).'::registerAllRoutes must be ' .
                'an array, StdClass or Traversable object'
            );
        }
        
        foreach ($iterable as $matchablePattern => $resource) {
            $this->addRoute($matchablePattern, $resource);
        }
    }
    
    /**
     * @return string
     */
    public function serialize() {
        return serialize($this->routes);
    }
    
    /**
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized) {
        $this->routes = unserialize($serialized);
    }
    
    public function count() {
        return $this->routes->count();
    }
    
    public function current() {
        return $this->routes->current();
    }
    
    public function key() {
        return $this->routes->key();
    }
    
    public function next() {
        return $this->routes->next();
    }
    
    public function rewind() {
        return $this->routes->rewind();
    }
    
    public function valid() {
        return $this->routes->valid();
    }
}
