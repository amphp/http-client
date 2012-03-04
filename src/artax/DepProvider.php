<?php

/**
 * Artax DepProvider Class File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * DepProvider Class
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class DepProvider extends Bucket implements ProviderInterface
  {
    /**
     * A DotNotation object for parsing dot-notation class names
     * @var DotNotation
     */
    protected $dotNotation;
    
    /**
     * An array of stored "singleton" dependencies
     * @var array
     */
    protected $shared;
    
    /**
     * A hash mapping dot notation class names to constructor method signatures
     * @var array
     */
    protected $constructorSigCache;
    
    /**
     * Initializes DotNotation object dependency
     * 
     * @param DotNotation $dotNotation A DotNotation object for class name parsing
     * 
     * @return void
     */
    public function __construct(DotNotation $dotNotation)
    {
      $this->shared = [];
      $this->constructorSigCache = [];
      $this->dotNotation = $dotNotation;
    }
    
    /**
     * Store a shared dependency
     * 
     * @param string $type     The dot-notated class name
     * @param mixed  $instance The shared dependency instance
     * 
     * @return DepProvider Returns object instance for method chaining
     * @throws \InvalidArgumentException If instance arg doesn't match
     *                                             specified type
     */
    public function setSharedDep($type, $instance)
    {
      $cls = $this->dotNotation->parse($type);
      if ( ! $instance instanceof $cls) {
        $msg = "Expected $cls instance: " . get_class($instance) . ' received';
        throw new \InvalidArgumentException($msg);
      }
      $this->shared[$type] = $instance;
      return $this;
    }
    
    /**
     * Clear a shared dependency
     * 
     * @param string $type     The dot-notated class name
     * 
     * @return DepProvider Returns object instance for method chaining
     */
    public function clearSharedDep($type)
    {
      if (isset($this->shared[$type])) {
        unset($this->shared[$type]);
      }
      return $this;
    }
    
    /**
     * Factory method for object instantiation
     * 
     * @param string $type   A dot notation class name
     * @param array  $custom An array of specific constructor arguments to use
     * 
     * @return mixed A dependency-injected object
     */
    public function make($type, array $custom=[])
    {
      $shared = (isset($this->params[$type]['_shared'])
        && TRUE === $this->params[$type]['_shared']);
      
      if ($shared && isset($this->shared[$type])) {
        return $this->shared[$type];
      }
      
      $specd = empty($this->params[$type]) ? [] : $this->params[$type];
      $obj   = $this->getInjectedInstance($type, $specd, $custom);
      
      if ($shared) {
        $this->shared[$type] = $obj;
      }
      
      return $obj;
    }
    
    /**
     * Return an instantiated object based on specified and custom dependencies
     * 
     * The dot notation class names specified in the config file are necessary
     * when a class constructor's method signature specifies an abstract class
     * or interface. When this occurs, reflection alone cannot allow dependency
     * instantiation. As a result, we need to manually specify the name of the
     * appropriate class to load for such instances.
     * 
     * @param string $type   A fully qualified and namespaced class name
     * @param array  $specd  An associative array of fully qualified dependency
     *                       class names needed for object instantiation
     * @param array  $custom An associative array of specific dependency objects
     *                       to use for object instantiation instead of new
     *                       dependency instances
     * 
     * @return mixed A dependency-injected object
     */
    protected function getInjectedInstance($type, array $specd, array $custom)
    {
      $class = $this->dotNotation->parse($type);
      $refl  = new \ReflectionClass($class);
      
      if (isset($this->constructorSigCache[$type])) {
        $args = $this->constructorSigCache[$type];
      } else {
        $args = $this->parseConstructorArgs($refl);
        $this->constructorSigCache[$type] = $args;
      }
      
      $deps  = [];
      
      foreach ($args as $key => $val) {
        if (isset($custom[$key])) {
          $deps[$key] = $custom[$key];
        } elseif (isset($specd[$key])) {
          $deps[$key] = $this->make($specd[$key]);
        } else {
          $deps[$key] = $this->make($val);
        }
      }
      $obj = $refl->newInstanceArgs($deps);
      
      return $obj;
    }
    
    /**
     * Parses a key/value array of argument names and types from a constructor
     * 
     * @param ReflectionClass $refl A reflection class instance
     * 
     * @return array Returns a key/value array of constructor arguments
     */
    protected function parseConstructorArgs(\ReflectionClass $refl)
    {
      $args = [];
      $p = '/Parameter\s#\d+\s\[\s<(?:optional|required)>\s([^\s]+)\s\$([^\s]+)\s\]/';
      if (preg_match_all($p, $refl->getConstructor(), $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
          $args[$m[2]] = $this->dotNotation->parse($m[1], TRUE);
        }
      }
      return $args;
    }
  }
}
