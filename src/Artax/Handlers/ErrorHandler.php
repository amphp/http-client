<?php

/**
 * Artax ErrorHandler Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Handlers;
  
/**
 * ErrorHandler Class
 * 
 * All PHP errors result in a `ErrorException` exception.
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * A boolean debug-level flag
     * @var bool
     */
    protected $debug;
    
    /**
     * Initialize error reporting settings and register the error handler
     * 
     * @param bool $debug
     * 
     * @return void
     */
    public function __construct($debug=TRUE)
    {
        $this->debug = (bool) $debug;
    }
    
    /**
     * Register the custom error handler and set error reporting levels
     * 
     * @return void
     */
    public function register()
    {
        if (!$this->debug) {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', FALSE);
        } else {
            // error_reporting already set to E_ALL by Artax.php boostrap
            ini_set('display_errors', TRUE);
        }
        set_error_handler([$this, 'handle']);
    }
    
    /**
     * Throw exceptions when PHP errors are raised
     * 
     * @param int    $errNo   The PHP error constant raised
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * 
     * @return void
     * @throws ErrorException On raised PHP error
     */
    public function handle($errNo, $errStr, $errFile, $errLine)
    {
        $levels = [
          E_WARNING           => 'Warning',
          E_NOTICE            => 'Notice',
          E_USER_ERROR        => 'User Error',
          E_USER_WARNING      => 'User Warning',
          E_USER_NOTICE       => 'User Notice',
          E_STRICT            => 'Runtime Notice',
          E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
          E_DEPRECATED        => 'Deprecated Notice',
          E_USER_DEPRECATED   => 'User Deprecated Notice'
        ];
        $msg = $levels[$errNo] . ": $errStr in $errFile on line $errLine";
        throw new \ErrorException($msg);
    }
}
