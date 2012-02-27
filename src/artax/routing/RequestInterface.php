<?php

/**
 * RequestInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage routing
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\routing {
  
  /**
   * RequestInterface
   * 
   * @category   artax
   * @package    core
   * @subpackage routing
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface RequestInterface
  {
    /**
     * Retrieve a request object's routable target (URI)
     */
    public function getTarget();
  }
}
