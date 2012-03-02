<?php

/**
 * Artax HttpResponse Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax\http {
  
  /**
   * HttpResponse Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpResponse extends \artax\controllers\Response
    implements HttpResponseInterface
  {
    /**
     * A key/value array of header parameters to send to the client
     * @var array
     */
    protected $headers = [];
    
    /**
     * Output the response to the client
     * 
     * Overrides parent to send headers prior to echoing the response body.
     * 
     * @return void
     */
    public function output()
    {
      $this->sendHeaders();
      echo $this->body;
    }
    
    /**
     * Add HTTP headers to send on response execution
     * 
     * @param string $headerStr  The header string to send
     * 
     * @return void
     */
    public function addHeader($headerStr)
    {
      $this->headers[] = $headerStr;
    }
    
    /**
     * Getter method for object's $headers array
     * 
     * @return array Returns array of headers added to the response
     */
    public function getHeaders()
    {
      return $this->headers;
    }
    
    /**
     * Outputs all headers to the client
     * 
     * @return void
     */
    public function sendHeaders()
    {
      foreach ($this->headers as $header) {
        header("$header");
      }
    }
  }
}
