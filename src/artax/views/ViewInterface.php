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
     * Assign an array of multiple template variables at once
     * 
     * @param array  $vars An associative key-value array for mass template
     *                     variable assignment
     */
    public function setAll(array $vars);
    
    /**
     * Fetch rendered template without outputting to client
     * 
     * @param string $tpl  The template to render
     * @param array  $vars An associative key-value array for mass template
     *                     variable assignment at render time
     */
    public function render($tpl, array $vars);
    
    /**
     * Output a rendered template
     * 
     * @param string $tpl  The template to display
     * @param array  $vars An associative key-value array for mass template
     *                     variable assignment at output time
     */
    public function output($tpl, array $vars);
  }
}
