<?php

/**
 * Artax RouteInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage routing
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\routing {
  
  /**
   * RouteInterface
   * 
   * @category   artax
   * @package    core
   * @subpackage routing
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface RouteInterface
  {
    /**
     * Getter method for route alias
     */
    public function getAlias();
    
    /**
     * Getter method for route target controller
     */
    public function getController();
    
    /**
     * Getter method for route argument constraints
     */
    public function getConstraints();
    
    /**
     * Getter method for generated route-matching pattern
     */
    public function getPattern();
  }
}
