<?php

/**
 * Artax HttpResponseInterface File
 * 
 * PHP version 5.3
 * 
 * @category   Artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace Artax\Http {
  
  /**
   * Resource Response Class
   * 
   * @category   Artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface HttpResponseInterface extends \Artax\Controllers\ResponseInterface
  {
    /**
     * Add HTTP headers to send on response execution
     * 
     * @param string $headerStr  The header string to send
     */
    public function addHeader($headerStr);
    
    /**
     * Getter method for object's $headers array
     */
    public function getHeaders();
    
    /**
     * Sends all headers to the client
     */
    public function sendHeaders();
  }
}
