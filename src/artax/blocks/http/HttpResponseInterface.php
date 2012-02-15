<?php

/**
 * Artax HttpResponseInterface File
 * 
 * PHP version 5.3
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax\blocks\http {
  
  /**
   * Resource Response Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface HttpResponseInterface extends \artax\ResponseInterface
  {
    /**
     * Add HTTP headers to send on response execution
     * 
     * @param string $name  The name of the header
     * @param string $value The header value
     */
    public function addHeader($name, $value);
    
    /**
     * Getter method for object's $headers array
     */
    public function getHeaders();
  }
}
