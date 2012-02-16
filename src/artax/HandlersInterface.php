<?php

/**
 * Artax HandlersInterface File
 *
 * PHP version 5.4
 *
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax {

  /**
   * Artax HandlersInterface
   *
   * Provides exception and shutdown handling functionality
   *
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface HandlersInterface
  {
    /**
     * The last chance handler for uncaught exceptions
     *
     * @param \Exception $e Exception object
     */
    public function exHandler(\Exception $e);

    /**
     * Handle unexpected fatal errors
     *
     * @return void
     */
    public function shutdown();
    
    /**
     * Outputs response to client when the requested route is not found
     */
    public function notFound();
    
    /**
     * Outputs response to client when an unexpected internal error is encountered
     */
    public function unexpectedError(\Exception $e);
    
    /**
     * Specify if full debug message should be shown for unexpected exceptions
     */
    public function setDebug($val);
  }
}
