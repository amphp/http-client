<?php

/**
 * HttpRequestInterface File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Http {
  
  /**
   * HttpRequestInterface
   * 
   * @category   Artax
   * @package    core
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface HttpRequestInterface extends \Artax\Routing\RequestInterface
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
