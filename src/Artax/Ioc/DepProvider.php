<?php

/**
 * Artax DepProvider Class File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Ioc;
  
/**
 * DepProvider Class
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
class DepProvider extends \Artax\Bucket implements ProviderInterface
{
    /**
     * A DotNotation object for parsing dot-notation class names
     * @var DotNotation
     */
    protected $dotNotation;
    
    /**
     * An array of dependencies shared across the lifetime of the container
     * @var array
     */
    protected $shared;
    
    /**
     * A hash mapping dot notation class names to constructor method signatures
     * @var array
     */
    protected $ctorSigCache;
    
    /**
     * Initializes DotNotation object dependency
     * 
     * @param DotNotation $dotNotation A DotNotation object for class name parsing
     * 
     * @return void
     */
    public function __construct(DotNotation $dotNotation)
    {
        $this->shared       = [];
        $this->ctorSigCache = [];
        $this->dotNotation  = $dotNotation;
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
        $shared = !empty($this->params[$type]['_shared']);
         
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
     * @return mixed Returns A dependency-injected object of the specified type
     */
    protected function getInjectedInstance($type, array $specd, array $custom)
    {
        $class = $this->dotNotation->parse($type);
        $refl  = new \ReflectionClass($class);
      
        if (isset($this->ctorSigCache[$type])) {
            $args = $this->ctorSigCache[$type];
        } else {
            $args = $this->parseConstructorArgs($refl);
            $this->ctorSigCache[$type] = $args;
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
        $p = '/Parameter\s#\d+\s\[\s<(?:optional|required)>'
            .'\s([^\s]+)\s\$([^\s]+)\s\]/';
            
        if (preg_match_all($p, $refl->getConstructor(), $m, PREG_SET_ORDER)) {
            foreach ($m as $arg) {
                $args[$arg[2]] = $this->dotNotation->parse($arg[1], TRUE);
            }
        }
        return $args;
    }
}
