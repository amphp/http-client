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
     * Note that if any parameters have the same name as parameters that already
     * exist in the container, the old values will be overwritten with those
     * specified in the new array.
     * 
     * If the optional `$useSetters` flag is set to `TRUE` the loader will
     * 
     * @param array $params     Bucket storage array
     * 
     * @return Object instance for method chaining
     * @throws exceptions\InvalidArgumentException On empty or non-string `$id`
     *                                             param key
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
     * Registers a dependency with the container
     * 
     * If the optional `$useSetters` property is set to `TRUE` this method will
     * use concrete setter methods to assign container params where applicable.
     * A check is made to see if a callable concrete setter method exists for
     * the specified container parameter. If so, the setter method is used to
     * store the parameter in place of simple assignment to the `$params` array.
     * Note that both `method_exists` AND `is_callable` are required to determine
     * a setter's existence due to the magic `__call` method's ability to create
     * magic getter and setter methods. Of course, this behavior is only relevant
     * in child classes specifying custom setter methods. There is no need for
     * `$useSetters` when dealing with direct instances of the Bucket class.
     * 
     * By convention, CamelCasing of custom setter method names is enforced. 
     * This means that the setter method for a property named "myProperty" must 
     * capitalize the first character of the desired property:
     * 
     *     $myProp = $container->setMyProperty() // correct
     *     $myProp = $container->setmyProperty() // not invoked for myProp param
     * 
     * Of course, if your property name starts with an uppercase letter (weird),
     * the setter name should already be correct. 
     * 
     * IMPORTANT: Note that it is incumbent upon the setter method to store the
     * param value in the protected `Bucket::$params` holder. This should be 
     * done via simple assignment as attempting to use the `Bucket::store` 
     * method inside a setter method while the `$useSetters` flag is enabled 
     * will result in an infinite loop:
     * 
     *     $this->params['myProp'] = 'value';
     * 
     * Be careful if using `Bucket::store` inside your setter methods that
     * you don't cause an infinite loop by using `$useSetters=TRUE`.
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
