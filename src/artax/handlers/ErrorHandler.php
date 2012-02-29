<?php

/**
 * Artax ErrorHandler Class File
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
   * ErrorHandler Class
   * 
   * All PHP errors result in a `exceptions\ErrorException` exception.
   * 
   * @category   artax
   * @package    core
   * @subpackage handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ErrorHandler implements ErrorHandlerInterface
  {
    /**
     * Throw exceptions when PHP errors are raised
     * 
     * @param int    $errNo   The PHP error constant raised
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * 
     * @return void
     * @throws \artax\exceptions\ErrorException On raised PHP error
     */
    public function handle($errNo, $errStr, $errFile, $errLine)
    {
      $levels = [
        E_WARNING           => 'Warning',
        E_NOTICE            => 'Notice',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED        => 'Deprecated Notice',
        E_USER_DEPRECATED   => 'User Deprecated Notice'
      ];
      $msg = $levels[$errNo] . ": $errStr in $errFile on line $errLine";
      throw new \artax\exceptions\ErrorException($msg);
    }
  }
}
