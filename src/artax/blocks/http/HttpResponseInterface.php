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
  interface HttpResponseInterface extends \artax\controllers\ResponseInterface
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
