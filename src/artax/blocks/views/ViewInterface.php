<?php

/**
 * Artax ViewInterface File
 * 
 * PHP version 5.3
 * 
 * @package    blocks
 * @subpackage views
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\views {
  
  /**
   * ViewInterface
   * 
   * @category   artax
   * @package    blocks
   * @subpackage views
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ViewInterface
  {
    /**
     * Template variable setter method
     * 
     * @param string $name Template variable name
     * @param mixed  $var  Template variable contents
     * 
     * @return mixed
     */
    public function setVar($name, $var);
    
    /**
     * Template variable getter method
     * 
     * @param string $name Template variable name
     * 
     * @return mixed Template variable contents
     */
    public function getVar($name);
    
    /**
     * Fetch rendered template without outputting
     * 
     * @return string
     */
    public function render();
    
    /**
     * Send rendered template output to STDOUT
     * 
     * @return void
     */
    public function output();
  }

}
