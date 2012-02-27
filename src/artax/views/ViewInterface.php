<?php

/**
 * Artax ViewInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage views
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\views {
  
  /**
   * ViewInterface
   * 
   * @category   artax
   * @package    core
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
     * @param mixed  $var  Template variable value
     */
    public function setVar($name, $var);
    
    /**
     * Template variable getter method
     * 
     * @param string $name Template variable name
     */
    public function getVar($name);
    
    /**
     * Fetch rendered template without outputting to client
     */
    public function render();
    
    /**
     * Output the rendered template
     */
    public function output();
  }
}
