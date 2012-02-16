<?php

/**
 * Artax Bucket Class File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * Bucket Class
   * 
   * Buckets are general purpose collection objects. The class implements
   * ArrayAccess and Iterator interfaces to allow easy array-type access to
   * stored properties.
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Bucket implements BucketInterface, \ArrayAccess, \Iterator
  {
    use BucketArrayAccessTrait;
    
    /**
     * Parameter storage array
     * @var array
     */
    protected $params;
    
    /**
     * Load a pre-existing array of container parameters
     * 
     * @param array $params     Bucket storage array
     * 
     * @return Object instance for method chaining
     */
    public function load(Array $params, $overwrite=FALSE)
    {
      $method = $overwrite ? 'set' : 'add';
      foreach ($params as $key => $val) {
        $this->$method($key, $val);
      }      
      return $this;
    }
    
    /**
     * Registers a parameter for storage in the bucket
     * 
     * @param string $id         Param identifier name
     * @param mixed  $param      Specified parameter value
     * 
     * @return Object instance for method chaining
     */
    public function set($key, $val)
    {
      $this->params[$key] = $val;
      return $this;
    }
    
    /**
     * Store a named parameter in the bucket ONLY if it doesn't already exist
     * 
     * @param string $key      Param identifier
     * @param mixed  $val      Specified parameter value
     * 
     * @return mixed Object instance for method chaining
     */
    public function add($key, $val)
    {
      if ( ! isset($this->params[$key])) {
        $this->params[$key] = $val;
      }
      return $this;
    }
    
    /**
     * Retrieve a parameter from the bucket storage
     * 
     * @param string $key      Param identifier
     * 
     * @return mixed Returns the value of the specified config directive
     * @throws exceptions\OutOfBoundsException On invalid config parameter key
     */
    public function get($key)
    {
      if (isset($this->params[$key])) {
        return $this->params[$key];
      }
      $msg = "The specified parameter does not exist: $key";
      throw new exceptions\OutOfBoundsException($msg);
    }
    
    /**
     * Convenience method to determine if a param exists in the container
     * 
     * @param string $id
     * 
     * @return bool `TRUE` if the param exists, `FALSE` if not
     */
    public function exists($id)
    {
      return isset($this->params[$id]) ? TRUE : FALSE;
    }
    
    /**
     * Remove a specified parameter from the bucket storage
     * 
     * @param string $key      Param identifier
     * 
     * @return mixed Object instance for method chaining
     */
    public function remove($key)
    {
      unset($this->params[$key]);
      return $this;
    }
    
    /**
     * Remove ALL non-default parameters from the bucket storage
     * 
     * @return Config Object instance for method chaining
     */
    public function clear()
    {
      $this->params = [];
      return $this;
    }
    
    /**
     * Retrieve ALL parameters from the bucket storage
     */
    public function all() {
      return $this->params;
    }
    
    /**
     * Retrieve a list of all current bucket key identifier names
     */
    public function keys()
    {
      return array_keys($this->params);
    }
    
    /**
     * Rewind the Iterator to the first element
     * 
     * @return void
     */
    public function rewind()
    {
      reset($this->params);
    }
    
    /**
     * Return the current element
     * 
     * @return mixed The value at the current array pointer location
     */
    public function current()
    {
      return current($this->params);
    }
    
    /**
     * Return the key at the current array pointer location
     * 
     * @return string The associative array key of the current pointer location
     */
    public function key()
    {
      return key($this->params);
    }
    
    /**
     * Move the array pointer forward to next element
     * 
     * @return void
     */
    public function next()
    {
      next($this->params);
    }
    
    /**
     * Checks if current array pointer position is valid
     * 
     * @return bool `FALSE` on invalid pointer or `TRUE` if valid position
     */
    public function valid()
    {
      return current($this->params) !== FALSE ? TRUE : FALSE;
    }
  }
}
