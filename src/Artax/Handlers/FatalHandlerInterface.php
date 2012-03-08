<?php

/**
 * Artax FatalHandlerInterface File
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
   * FatalHandlerInterface
   *
   * Provides an interface for uncaught exception and shutdown handling.
   *
   * @category   Artax
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
