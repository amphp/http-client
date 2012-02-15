<?php

/**
 * Artax RequestInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * RequestInterface Interface
   * 
   * @category Artax
   * @package  Core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface RequestInterface
  {
    /**
     * Retrieve the Request object's routable target
     * 
     * @return string Returns requested app target
     */
    public function getTarget();
    
    /**
     * Retrieve the request method
     * 
     * This method is used by the built-in matcher to apply HTTP method match
     * constraints for specific routes. For matcher classes dealing with HTTP
     * requests this method should return the HTTP method (GET, POST, PUT, etc.)
     * used to make the request.
     * 
     * @return string Returns request method
     */
    public function getMethod();
  }
}
