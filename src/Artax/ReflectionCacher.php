<?php

/**
 * ReflectionCacher Interface File
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
 * Defines an interface contract for caching/pooling reflections
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface ReflectionCacher
{
    /**
     * Retrieves and caches the ReflectionClass objects
     * 
     * @param string $className The class we want to reflect
     */
    public function getClass($className);
    
    /**
     * Retrieves and caches the class's constructor ReflectionMethod
     * 
     * @param string $className The class whose constructor we want to reflect
     */
    public function getConstructor($className);
    
    /**
     * Retrieves and caches constructor parameters for the given class name
     * 
     * @param string $className The name of the class whose constructor 
     *                          parameters we'd like to retrieve
     */
    public function getConstructorParameters($className);
    
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
     */
    public function getTypehint(ReflectionParameter $reflParam);
}
