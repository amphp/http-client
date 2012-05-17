<?php

/**
 * ErrorHandlerInterface
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Core\Handlers;

/**
 * Provides an interface for specifying custom error handlers.
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface ErrorsInterface
{
    /**
     * Register the custom error handler function
     */
    public function register();
    
    /**
     * Method to handle raised PHP errors
     * 
     * @param int    $errNo   The PHP error constant raised
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     */
    public function handle($errNo, $errStr, $errFile, $errLine);
}
