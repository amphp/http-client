<?php

/**
 * ResponseControllerInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * ResponseControllerInterface
   * 
   * @category   artax
   * @package    core
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
