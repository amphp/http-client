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
     * Setter method for template name/path
     * 
     * @param string $tpl The template to use for rendering
     */
    public function setTemplate($tpl);
    
    /**
     * Template variable setter method
     * 
     * @param string $name Template variable name
     * @param mixed  $var  Template variable contents
     */
    public function setVar($name, $var);
    
    /**
     * Template variable getter method
     * 
     * @param string $name Template variable name
     */
    public function getVar($name);
    
    /**
     * Fetch rendered template without outputting
     */
    public function render();
    
    /**
     * Send rendered template output to STDOUT
     */
    public function output();
  }

}
