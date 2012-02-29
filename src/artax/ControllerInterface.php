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
  interface ControllerInterface extends \artax\events\NotifierInterface
  {
    /**
     * The controller's "work" method
     */
    public function exec();
  }
}
