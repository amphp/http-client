<?php

/**
 * Artax TerminationInterface File
 *
 * PHP version 5.4
 *
 * @category   Artax
 * @package    Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace Artax\Handlers {

  /**
   * TerminationInterface
   *
   * Provides an interface for uncaught exception and shutdown handling.
   *
   * @category   Artax
   * @package    Handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface TerminationInterface
  {
    /**
     * Register the custom exception and shutdown handler functions
     */
    public function register();
    
    /**
     * The last chance handler for uncaught exceptions
     *
     * @param Exception $e Exception object
     */
    public function exHandler(\Exception $e);

    /**
     * Handle unexpected fatal errors
     *
     * @return void
     */
    public function shutdown();
  }
}
