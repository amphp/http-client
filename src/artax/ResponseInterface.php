<?php

/**
 * Artax ResponseInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax {
  
  /**
   * ResponseInterface
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ResponseInterface
  {
    /**
     * Setter method for object's $body property
     * 
     * @param string $body Response body text
     */
    public function setBody($body);
    
    /**
     * Getter method for object's $body property
     */
    public function getBody();
    
    /**
     * Output the response to the client
     */
    public function exec();
  }
}
