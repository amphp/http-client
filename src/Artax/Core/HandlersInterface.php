<?php

/**
 * Artax HandlersInterface File
 *
 * PHP version 5.4
 *
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Core;
use Exception;

/**
 * Specifies an interface for error, uncaught exception and shutdown handlers
 *
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface HandlersInterface
{
    /**
     * Method to handle raised PHP errors
     * 
     * @param int    $errNo   The PHP error constant raised
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     */
    public function error($errNo, $errStr, $errFile, $errLine);
    
    /**
     * The last chance handler for uncaught exceptions
     *
     * @param Exception $e Exception object
     */
    public function exception(Exception $e);
    
    /**
     * Register the custom error, exception and shutdown handlers
     */
    public function register();

    /**
     * Handle unexpected fatal errors
     *
     * @return void
     */
    public function shutdown();
}
