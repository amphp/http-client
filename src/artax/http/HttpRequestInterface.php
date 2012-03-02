<?php

/**
 * HttpRequestInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\http {
  
  /**
   * HttpRequestInterface
   * 
   * @category   artax
   * @package    core
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface HttpRequestInterface extends \artax\routing\RequestInterface
  {
    /**
     * Retrieve the request method
     * 
     * This method is used by the HTTP matcher to apply HTTP method match
     * constraints for specific routes.
     * 
     * @return string Returns HTTP request method
     */
    public function getMethod();
  }
}
