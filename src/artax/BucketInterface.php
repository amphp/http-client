<?php

/**
 * Artax BucketInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * BucketInterface Interface
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface BucketInterface
  {
    /**
     * Load an array of key-value container parameters
     * 
     * @param array $params    An array of key-value container parameters
     */
    public function load(array $params);
    
    /**
     * Store a named parameter in the bucket
     * 
     * @param string $key      Param identifier
     * @param mixed  $val      Specified parameter value
     */
    public function set($key, $val);
    
    /**
     * Store a named parameter in the bucket ONLY if it doesn't already exist
     * 
     * @param string $key      Param identifier
     * @param mixed  $val      Specified parameter value
     */
    public function add($key, $val);
    
    /**
     * Retrieve a parameter from the bucket storage
     * 
     * @param string $key      Param identifier
     */
    public function get($key);
    
    /**
     * Determine if a parameter exists in the bucket storage
     * 
     * @param string $key      Param identifier
     */
    public function exists($key);
    
    /**
     * Remove a specified parameter from the bucket storage
     * 
     * @param string $key      Param identifier
     */
    public function remove($key);
    
    /**
     * Remove ALL parameters from the bucket storage
     */
    public function clear();
    
    /**
     * Retrieve ALL parameters from the bucket storage
     */
    public function all();
    
    /**
     * Retrieve a list of all current bucket key identifier names
     */
    public function keys();
  }
}
