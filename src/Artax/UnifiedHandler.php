<?php

/**
 * UnifiedHandler File
 *
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    ${license.txt}
 * @version    ${project.version}
 */

namespace Artax;
use Exception;

/**
 * Specifies interface for a unified error, exception and shutdown handler
 *
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface UnifiedHandler {
    
    /**
     * Register the custom error, exception and shutdown handler functions
     */
    function register();
    
    /**
     * Method to handle raised PHP errors
     * 
     * @param int    $errNo   The PHP error constant raised
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     */
    function error($errNo, $errStr, $errFile, $errLine);
    
    /**
     * The last chance handler for uncaught exceptions
     *
     * @param Exception $e Exception object
     */
    function exception(Exception $e);

    /**
     * Handle unexpected fatal errors
     *
     * @return void
     */
    function shutdown();
    
}
