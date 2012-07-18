<?php
/**
 * StdRoute Class File
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Routing;

/**
 * A value object consisting of matchable pattern and an associated resource target
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class StdRoute implements Route {
    
    /**
     * @var string
     */
    private $pattern;
    
    /**
     * @var mixed
     */
    private $resource;
    
    /**
     * @param string $matchablePattern
     * @param mixed $resource
     * @return void
     */
    public function __construct($matchablePattern, $resource) {
        $this->setPattern($matchablePattern);
        $this->setResource($resource);
    }
    
    /**
     * @return string
     */
    public function getPattern() {
        return $this->pattern;
    }
    
    /**
     * @param string $matchablePattern
     * @return void
     */
    public function setPattern($matchablePattern) {
        $this->pattern = '/' . ltrim($matchablePattern, '/');
    }
    
    /**
     * @return mixed
     */
    public function getResource() {
        return $this->resource;
    }
    
    /**
     * @param mixed $resource
     * @return void
     */
    public function setResource($resource) {
        $this->resource = $resource;
    }
    
    /**
     * @return string
     */
    public function serialize() {
        return serialize(array($this->resource, $this->pattern));
    }
    
    /**
     * @param string $serialized A serialized representation of the object
     * @return void
     */
    public function unserialize($serialized) {
        list($this->resource, $this->pattern) = unserialize($serialized);
    }
}
