<?php

/**
 * Artax UsesRequestTrait Trait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * Artax UsesRequestTrait Trait
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait UsesRequestTrait
  {
    /**
     * RequestInterface object instance
     * @var RequestInterface
     */
    protected $request;
    
    /**
     * Setter method for protected `$request` property
     * 
     * @param RequestInterface $request Request object instance
     * 
     * @return mixed Object instance for method chaining
     */
    public function setRequest(RequestInterface $request)
    {
      $this->request = $request;
      return $this;
    }
    
    /**
     * Getter method for protected `$request` property
     * 
     * @return RequestInterface Returns RequestInterface object or `NULL` if not
     *                          assigned
     */
    public function getRequest()
    {
      return $this->request;
    }
  }
}
