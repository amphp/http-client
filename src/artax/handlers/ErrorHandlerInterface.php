<?php

/**
 * ErrorHandlerInterface
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\handlers {

  /**
   * ErrorHandlerInterface
   * 
   * @category   artax
   * @package    core
   * @subpackage handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ErrorHandlerInterface
  {
    /**
     * Method to handle raised PHP errors
     */
    public function handle($errno, $errstr, $errfile, $errline);
  }
}
