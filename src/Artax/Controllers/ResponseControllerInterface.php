<?php

/**
 * ResponseControllerInterface File
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
   * ResponseControllerInterface
   * 
   * @category   Artax
   * @package    core
   * @subpackage controllers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ResponseControllerInterface extends ControllerInterface
  {
    /**
     * Getter method for $response object property
     */
    public function getResponse();
  }
}
