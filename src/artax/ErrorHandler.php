<?php

/**
 * Artax ErrorHandler Class File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * ErrorHandler Class
   * 
   * Exposes `handle` method to deal with raised PHP errors. All PHP errors
   * result in an `exceptions\ErrorException` exception being thrown.
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ErrorHandler implements ErrorHandlerInterface
  {
    /**
     * Throw exceptions when PHP errors are raised
     * 
     * @return void
     * @throws exceptions\ErrorException On PHP error occurrence
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
      throw new exceptions\ErrorException($msg);
    }
  }
}
