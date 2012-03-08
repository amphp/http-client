<?php

/**
 * Artax BucketArrayAcessTrait Trait File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax {

  /**
   * Artax BucketArrayAcessTrait
   * 
   * @category   Artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait BucketArrayAccessTrait
  {
    /**
     * Implements ArrayAccess interface to assign params
     * 
     * @param mixed $offset Params identifier
     * @param mixed $value  Params entity value
     * 
     * @return void
     */
    public function offsetSet($offset, $value)
    {
      $this->set($offset, $value);
    }
    
    /**
     * Implements ArrayAccess interface to determine if a param name exists
     * 
     * @param mixed $offset Params identifier
     * 
     * @return bool `TRUE` if the parameter exists or `FALSE` if it doesn't
     */
    public function offsetExists($offset)
    {
      return $this->exists($offset);
    }
    
    /**
     * Implements ArrayAccess interface to unset a specified parameter
     * 
     * @param mixed $offset Params identifier
     * 
     * @return void
     */
    public function offsetUnset($offset)
    {
      $this->remove($offset);
    }
    
    /**
     * Implements ArrayAccess interface to retrieve a specified parameter value
     * 
     * @param mixed $offset Params identifier
     * 
     * @return mixed Parameter value if it exists or `NULL` otherwise
     */
    public function offsetGet($offset)
    {
      return $this->get($offset);
    }
  }
}
