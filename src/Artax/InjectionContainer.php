<?php

/**
 * Artax InjectionContainer Interface File
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
  
/**
 * Specifies a front-facing interface for dependency providers.
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface InjectionContainer {
    
    /**
     * Factory method for auto-injecting dependencies upon instantiation
     * 
     * @param string $class  Class name
     * @param mixed  $custom An optional array specifying custom instantiation
     *                       parameters for this construction
     */
    function make($class, array $custom);
    
    /**
     * Defines custom instantiation parameters for the specified class
     * 
     * @param string $class      Class name
     * @param array  $definition An array specifying custom instantiation params
     */
    function define($class, array $definition);
    
    /**
     * Defines multiple custom instantiation parameters at once
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass
     *                        or ArrayAccess instance
     */
    function defineAll($iterable);
    
    /**
     * Clear the injection definition for the specified class
     * 
     * @param string $class Class name
     */
    function remove($class);
    
    /**
     * Clear all injection definitions from the container
     */
    function removeAll();
    
    /**
     * Forces re-instantiation of a shared class the next time it is requested
     * 
     * @param string $class Class name
     */
    function refresh($class);
    
    /**
     * Determines if a shared instance of the specified class is stored
     * 
     * @param string $class Class name
     */
    function isShared($class);
    
    /**
     * Determines if an injection definition exists for the specified class
     * 
     * @param string $class Class name
     */
    function isDefined($class);
    
}
