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
     * Retrieve a request object's routable target
     * 
     * For an HTTP request, the target would be the REQUEST_URI. CLI applications
     * may not require routing; however, the facility is provided nonetheless.
     */
    public function getTarget();
  }
}
