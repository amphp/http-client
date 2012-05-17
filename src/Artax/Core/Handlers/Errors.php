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

namespace Artax\Core\Handlers;

use ErrorException,
    Artax\Core\MediatorInterface;

/**
 * ErrorHandler Class
 * 
 * Defines a custom error handling function that notifies registered event 
 * listeners upon any raised PHP error (except fatals, of course). Fatal 
 * errors cannot be handled by custom error handlers and are handled by the
 * `Termination` class.
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class Errors implements ErrorsInterface
{
    /**
     * A boolean debug-level flag
     * @var bool
     */
    protected $debug;
    
     /**
     * An event mediator instance
     * @var Mediator
     */
    protected $mediator;
    
    /**
     * Specify debug output flag and register exception/shutdown handlers
     * 
     * @param bool $debug A boolean debug output flag
     * 
     * @return void
     */
    public function __construct(MediatorInterface $mediator, $debug)
    {
        $this->mediator = $mediator;
        $this->debug    = (bool) $debug;
    }
    
    /**
     * Register the custom error handler
     * 
     * @return ErrorHandler Returns object instance for method chaining.
     */
    public function register()
    {
        set_error_handler([$this, 'handle']);
        return $this;
    }
    
    /**
     * Send an event when PHP errors are raised
     * 
     * In the event a PHP error is raised, the handler creates an `ErrorException` 
     * object with a summary message and integer code matching the value of the
     * raised error's constant. Listeners can choose what to do, if anything,
     * with the generated exception object.
     * 
     * Because all errors are reported, the error event allows you to specify
     * event listeners that silently log low-priority errors such as 
     * `E_DEPRECATED` and `E_STRICT` as needed in production environments.
     * Generally, listeners can simply throw the `ErrorException` object for
     * higher-priority errors passed to `error` event listeners.
     * 
     * When no error event listeners are specified: ALL raw error messages 
     * are output to the client if DEBUG mode is turned on. If DEBUG mode is
     * turned off and no error listeners are registered, non-fatal PHP errors
     * are silently ignored.
     * 
     * @param int    $errNo   The PHP error constant
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * 
     * @return void
     * @notifies error(ErrorException, bool)
     */
    public function handle($errNo, $errStr, $errFile, $errLine)
    {
        $msg = "$errStr in $errFile on line $errLine";
        $e   = new ErrorException($msg, $errNo);
        if (!$this->mediator->notify('error', $e, $this->debug) && $this->debug) {
            echo $msg;
        }
    }
}
