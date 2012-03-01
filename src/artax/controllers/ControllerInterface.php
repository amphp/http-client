<?php

/**
 * ControllerInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage controllers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\controllers {

  /**
   * ControllerInterface
   * 
   * @category   artax
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
