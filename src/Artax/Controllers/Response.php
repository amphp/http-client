<?php

/**
 * Artax Response Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage controllers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace Artax\Controllers {
  
  /**
   * Response Class
   * 
   * @category   Artax
   * @package    core
   * @subpackage controllers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Response implements ResponseInterface
  {
    /**
     * The response body text
     * @var string
     */
    protected $body = '';
    
    /**
     * Setter method for object's $str property
     * 
     * @param string $str Response body string
     * 
     * @return Response Returns object instance for method chaining
     */
    public function set($str)
    {
      $this->body = $str;
      return $this;
    }
    
    /**
     * Append method for object's $str property
     * 
     * @param string $str Response body string
     * 
     * @return Response Returns object instance for method chaining
     */
    public function append($str)
    {
      $this->body .= $str;
      return $this;
    }
    
    /**
     * Prepend method for object's $str property
     * 
     * @param string $str Response body string
     * 
     * @return Response Returns object instance for method chaining
     */
    public function prepend($str)
    {
      $this->body = $str . $this->body;
      return $this;
    }
    
    /**
     * Getter method for object's $str property
     * 
     * @return string Body text
     */
    public function get()
    {
      return $this->body;
    }
    
    /**
     * Output the response to the client
     * 
     * @return void
     */
    public function output()
    {
      echo $this->body;
    }
    
    /**
     * Return the response body when object accessed as a string
     * 
     * This method has the same result as `Response::get`
     * 
     * @return string Body text
     */
    public function __toString()
    {
      return $this->body;
    }
  }
}
