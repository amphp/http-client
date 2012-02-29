<?php

/**
 * Artax BucketSettersTrait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * Artax BucketSettersTrait
   * 
   * The trait enables the use of custom setter methods for individual Bucket
   * parameter values in Bucket classes. 
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait BucketSettersTrait
  {
    /**
     * Enforces setter methods when storing parameter keys
     * 
     * @param string $key      Param identifier name
     * @param mixed  $val      Specified parameter value
     * 
     * @return Bucket Object instance for method chaining
     */
    public function set($key, $val)
    {
      $setter = 'set'.ucfirst($key);
      if (method_exists($this, $setter)) {
        $callable = [$this, $setter];
        $callable($val);
      } else {
        $this->params[$key] = $val;
      }
      return $this;
    }
    
    /**
     * Enforces setter methods when adding parameter keys
     * 
     * @param string $key      Param identifier
     * @param mixed  $val      Specified parameter value
     * 
     * @return mixed Object instance for method chaining
     */
    public function add($key, $val)
    {
      if ( ! isset($this->params[$key])) {
        $this->set($key, $val);
      }
      return $this;
    }
  }
}
