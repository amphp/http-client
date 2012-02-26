<?php

/**
 * Artax FatalHandlerInterface File
 *
 * PHP version 5.4
 *
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax {

  /**
   * Artax FatalHandlerInterface
   *
   * Provides exception and shutdown handling functionality
   *
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface FatalHandlerInterface
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
     * Assign the controller to use for uncaught exceptions
     * 
     * @param ExControllerInterface $exController Controller object
     */
    public function setExController(ExControllerInterface $exController);
  }
}
