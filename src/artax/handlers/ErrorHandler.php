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
     * @return void
     * @throws \artax\exceptions\ErrorException On raised PHP error
     */
    public function handle($errno, $errstr, $errfile, $errline)
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
      $msg = $levels[$errno] . ": $errstr in $errfile on line $errline";
      throw new \artax\exceptions\ErrorException($msg);
    }
  }
}
