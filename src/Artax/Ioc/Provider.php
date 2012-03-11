<?php

/**
 * Artax Provider Class File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Ioc;
  
/**
 * Provider Class
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 * @todo     Add full class level documentation
 */
class Provider implements ProviderInterface
{
    /**
     * An array of custom class instantiation parameters
     * @var array
     */
    protected $params;
    
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
     * 
     */
    public function add($dotClassStr, $definition)
    {
        if (!($definition instanceof \StdClass
            || is_array($definition)
            || $definition instanceof \Traversable))
        {
            throw new \InvalidArgumentException(
                'Argument 2 passed to add must be an array, Traversable '
                . 'or StdClass instance'
            );
        }
        $this->params[$dotClassStr] = $definition;
    }
    
    /**
     * 
     */
    public function addAll($iterable)
    {
        if (!($iterable instanceof \StdClass
            || is_array($iterable)
            || $iterable instanceof \Traversable))
        {
            throw new \InvalidArgumentException(
                'Argument 1 passed to addAll must be an array, Traversable '
                . 'or StdClass instance'
            );
        }
        
        $added = 0;
        foreach ($iterable as $dotClassStr => $definition) {
            $this->add($dotClassStr, $definition);
            ++$added;
        }
        return $added;
    }
    
    /**
     * Factory method for object instantiation
     * 
     * @param string $dotStr   A dot notation class name
     * 
     * @return mixed A dependency-injected object
     */
    public function make($dotStr)
    {
        if (isset($this->params[$dotStr])) {
            $param = $this->params[$dotStr] instanceof \StdClass
                ? (array) $this->params[$dotStr]
                : $this->params[$dotStr];
            $shared = !empty($param['_shared']);
        } else {
            $param  = NULL;
            $shared = FALSE;
        }
         
        if ($shared && isset($this->shared[$dotStr])) {
            return $this->shared[$dotStr];
        }

        $obj = $this->getInjectedInstance($dotStr, $param ?: []);
          
        if ($shared) {
            $this->shared[$dotStr] = $obj;
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
     * appropriate class to load.
     * 
     * @param string $type   A fully qualified and namespaced class name
     * @param array  $specd  An associative array of fully qualified dependency
     *                       class names needed for object instantiation
     * 
     * @return mixed Returns A dependency-injected object of the specified type
     */
    protected function getInjectedInstance($type, array $specd)
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
            if (isset($specd[$key])) {
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
