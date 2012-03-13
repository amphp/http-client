<?php

/**
 * Artax ProviderInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Ioc;
  
/**
 * ProviderInterface
 * 
 * Specifies a front-facing interface for dependency providers.
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
interface ProviderInterface
{
    /**
     * Factory method for auto-injecting dependencies upon instantiation
     * 
     * @param string $dotStr A dot-notation class name
     * @param mixed  $custom An optional array or ArrayAccess instance specifying
     *                       custom instantiation parameters for this construction
     */
    public function make($dotStr, $custom);
    
    /**
     * Defines custom instantiation parameters for the specified class
     * 
     * @param string $dotStr     The relevant dot-notation class name
     * @param mixed  $definition An array, StdClass or ArrayAccess instance
     */
    public function define($dotStr, $definition);
    
    /**
     * Defines multiple custom instantiation parameters at once
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass
     *                        or ArrayAccess instance
     */
    public function defineAll($iterable);
    
    /**
     * Clear the injection definition for the specified class
     * 
     * @param string $dotStr A dot-notation class name
     */
    public function remove($dotStr);
    
    /**
     * Clear all injection definitions from the container
     */
    public function removeAll();
    
    /**
     * Forces re-instantiation of a shared class the next time it is requested
     * 
     * @param string $dotStr The dot-notation class name to refresh
     */
    public function refresh($dotStr);
}
