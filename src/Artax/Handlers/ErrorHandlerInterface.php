<?php

/**
 * ErrorHandlerInterface
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Handlers {

  /**
   * ErrorHandlerInterface
   * 
   * @category   Artax
   * @package    core
   * @subpackage handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ErrorHandlerInterface
  {
    /**
     * Method to handle raised PHP errors
     * 
     * @param int    $errNo   The PHP error constant raised
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     */
    public function handle($errNo, $errStr, $errFile, $errLine);
  }
}
