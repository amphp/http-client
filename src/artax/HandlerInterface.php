<?php

/**
 * Artax HandlerInterface File
 *
 * PHP version 5.4
 *
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax {

  /**
   * Artax HandlerInterface
   *
   * Provides exception and shutdown handling functionality
   *
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface HandlerInterface
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
  }
}
