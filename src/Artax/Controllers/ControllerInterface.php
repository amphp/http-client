<?php

/**
 * ControllerInterface File
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
   * ControllerInterface
   * 
   * @category   Artax
   * @package    core
   * @subpackage controllers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ControllerInterface
  {
    /**
     * The controller's "work" method
     */
    public function exec();
  }
}
