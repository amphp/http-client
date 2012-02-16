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
    
    public function notFound();
    
    public function unexpectedError(\Exception $e);
    
    public function setDebug($val);
  }
}
