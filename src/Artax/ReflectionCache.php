<?php

/**
 * ReflectionCache Class File
 * 
 * PHP version 5.3
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    All code subject to the ${license.name}
 * @version    ${project.version}
 */

namespace Artax;

use SplObjectStorage,
    ReflectionClass,
    ReflectionParameter;

/**
 * Caches reflection results to minimize reflection performance impact
 * 
 * Computationally, Reflection can be slow(ish) ... so we cache the results.
 * The ReflectionCache class acts as a wrapper to reflection calls. When
 * a reflection object is retrieved the class caches the object so that
 * future reflection attempts can reuse the same instance instead of repeating
 * the same (slow) operation multiple times.
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class ReflectionCache implements ReflCacher
{
    /**
     * An array mapping class names reflected class objects
     * @var array
     */
    private $classes = array();
    
    /**
     * An array mapping class names to reflected constructor method
     * @var array
     */ 
    private $constructors = array();
    
    /**
     * An array mapping class names to an array of their constructor parameters
     * @var array
     */ 
    private $ctorParams = array();
    
    /**
     * An storage object matching reflection parameters to their typehints
     * @var SplObjectStorage
     */
    private $typehints;
    
    /**
     * Retrieves and caches the ReflectionClass objects
     * 
     * @param string $className The class we want to reflect
     * 
     * @return ReflectionMethod Returns the reflected class instance
     * @throws ReflectionException If the class can't be found or autoloaded
     */
    public function getClass($className)
    {
        $lowClass = strtolower($className);
        
        if (isset($this->classes[$lowClass])) {
            return $this->classes[$lowClass];
        }
        
        $reflClass = new ReflectionClass($className);
        $this->classes[$lowClass] = $reflClass;
        
        return $reflClass;
    }
    
    /**
     * Retrieves and caches the class's constructor ReflectionMethod
     * 
     * @param string $className The class whose constructor we want to reflect
     * 
     * @return ReflectionMethod Returns the reflected constructor or NULL if
     *                          the specified class has no constructor.
     */
    public function getConstructor($className)
    {
        $lowClass = strtolower($className);
        
        if (isset($this->constructors[$lowClass])
            || array_key_exists($lowClass, $this->constructors)
        ) {
            return $this->constructors[$lowClass];
        }
        
        $reflClass = $this->getClass($className);
        $reflCtor  = $reflClass->getConstructor();
        
        $this->constructors[$lowClass] = $reflCtor;
        
        return $reflCtor;
    }
    
    /**
     * Retrieves and caches constructor parameters for the given class name
     * 
     * @param string $className The name of the class whose constructor 
     *                          parameters we'd like to retrieve
     * 
     * @return array Returns an array of ReflectionParameter objects or 
     *               NULL if no constructor exists for the class.
     */
    public function getCtorParams($className)
    {
        $lowClass = strtolower($className);
        
        if (isset($this->ctorParams[$lowClass])
            || array_key_exists($lowClass, $this->ctorParams)
        ) {
            return $this->ctorParams[$lowClass];
        }
        
        if ($reflCtor = $this->getConstructor($className)) {
            $ctorParams = $reflCtor->getParameters();
        } else {
            $ctorParams = NULL;
        }
        
        $this->ctorParams[$lowClass] = $ctorParams;
        
        return $ctorParams;
    }
    
    /**
     * Retrieves the class typehint from a given ReflectionParameter
     * 
     * There is no way to directly access a parameter's typehint without
     * instantiating a new ReflectionClass instance and calling its getName()
     * method. This method stores the results of this approach so that if
     * the same parameter typehint or ReflectionClass is needed again we
     * already have it cached.
     * 
     * @param ReflectionParameter $reflParam a ReflectionParameter object
     * 
     * @return string Returns the typehinted class name of the given parameter
     *                or NULL if none exists.
     */
    public function getTypehint(ReflectionParameter $reflParam)
    {
        if (!$this->typehints) {
            $this->typehints = new SplObjectStorage;
        }
        
        if ($this->typehints->contains($reflParam)) {
            return $this->typehints->offsetGet($reflParam);
        }
        
        if ($reflClass = $reflParam->getClass()) {
            $className = $reflClass->getName();
            $lowClass  = strtolower($className);
            if (!isset($this->classes[$lowClass])) {
                $this->classes[$lowClass] = $reflClass;
            }
            $typehint = $className;
        } else {
            $typehint = NULL;
        }
        
        $this->typehints->attach($reflParam, $typehint);
        
        return $typehint;
    }
}
