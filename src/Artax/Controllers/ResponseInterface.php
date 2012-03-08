<?php

/**
 * Artax ResponseInterface File
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
   * ResponseInterface
   * 
   * @category   Artax
   * @package    core
   * @subpackage controllers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ResponseInterface
  {
    /**
     * Setter method for object's $str property
     * 
     * @param string $str Response body string
     */
    public function set($str);
    
    /**
     * Appends data to the object's protected $str property
     * 
     * @param string $str Response body string
     */
    public function append($str);
    
    /**
     * Prepend method for object's $str property
     * 
     * @param string $str Response body string
     */
    public function prepend($str);
    
    /**
     * Getter method for object's $str property
     */
    public function get();
    
    /**
     * Output the response to the client
     */
    public function output();
    
    /**
     * Return the response body when object accessed as a string
     */
    public function __toString();
  }
}
