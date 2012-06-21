<?php

/**
 * Artax InjectionContainer Interface File
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    ${license.txt}
 * @version    ${project.version}
 */

namespace Artax;
  
/**
 * Specifies a front-facing interface for dependency injection providers
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface InjectionContainer {
    
    /**
     * Auto-injects dependencies for managed class instantiation
     * 
     * @param string $class
     * @param array  $customDefinition
     */
    function make($className, array $customDefinition);
    
    /**
     * Defines custom instantiation parameters for the specified class
     * 
     * @param string $class
     * @param array  $definition
     */
    function define($className, array $definition);
    
    /**
     * Defines multiple custom instantiation parameters at once
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass
     *                        or ArrayAccess instance
     */
    function defineAll($iterable);
    
    /**
     * Determines if an injection definition exists for the specified class
     * 
     * @param string $className
     * @return bool
     */
    function isDefined($className);
    
    /**
     * Defines an implementation class for all occurrences of a given interface or abstract
     * 
     * @param string $nonConcreteType
     * @param string $className
     */
    function setImplementation($nonConcreteType, $className);
    
    /**
     * Retrieves the assigned implementation for the non-concrete type
     * 
     * @param string $nonConcreteType
     * 
     * @return string Returns the assigned concrete implementation class
     */
    function getImplementation($nonConcreteType);
    
    /**
     * Determines if an implementation definition exists for the non-concrete type
     * 
     * @param string $nonConcreteType
     * 
     * @return bool
     */
    function hasImplementation($nonConcreteType);
    
    /**
     * Clears an existing implementation class for the specified non-concrete type
     * 
     * @param string $nonConcreteType
     */
    function clearImplementation($nonConcreteType);
    
    /**
     * Clears a previously-defined injection definition
     * 
     * @param string $className
     */
    function remove($className);
    
    /**
     * Clears all injection definitions from the container
     */
    function removeAll();
    
    /**
     * Shares an instance of the specified class
     * 
     * @param string $className
     * @param mixed  $instance
     */
    function share($className, $instance);
    
    /**
     * Unshares the specified class
     * 
     * @param string $className
     */
    function unshare($className);
    
    /**
     * Determines if a given class name is marked as shared
     * 
     * @param string $className
     * 
     * @return bool
     */
    function isShared($className);
    
    /**
     * Forces re-instantiation of a shared class the next time it is requested
     * 
     * @param string $className
     */
    function refresh($className);
    
}
