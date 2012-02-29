<?php

/**
 * Artax FatalHandlerInterface File
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
   * FatalHandlerInterface
   *
   * Provides an interface for uncaught exception and shutdown handling.
   *
   * @category   artax
   * @package    core
   * @subpackage handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface FatalHandlerInterface
  {
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
