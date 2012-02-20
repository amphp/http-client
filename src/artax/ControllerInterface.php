<?php

/**
 * ControllerInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * ControllerInterface
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ControllerInterface
  {
    /**
     * The controller's "work" method
     */
    public function exec();
    
    /**
     * A magic invocation method to execute the controller's work method
     */
    public function __invoke();
  }
}
