<?php
/**
 * Handler Class File
 *
 * @category    Artax
 * @package     Core
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax;

use Exception,
    ErrorException;

/**
 * Provides error, uncaught exception and shutdown handling
 *
 * The Handlers class uses an event mediator to enable unified, evented
 * handling for PHP errors, fatal shutdowns and uncaught exceptions as well as
 * normal shutdown events.
 * 
 * If you're a seasoned PHP developer you'll be used to specifying your own 
 * custom exception handler and shutdown functions via `set_exception_handler`
 * and `register_shutdown_function`. Artax negates the need for manually setting
 * these handlers and instead provides a single unified error handling event:
 * the system `exception` event.
 * 
 * All PHP errors (subject to error reporting levels), uncaught exceptions and 
 * fatal runtime errors trigger the `exception` event.
 * 
 * The system `shutdown` event works in the same manner: simply add listeners
 * for the `shutdown` event to perform any actions you would otherwise accomplish
 * by registering custom shutdown handlers in PHP. Any `shutdown` listeners
 * will be invoked *after* script execution terminates regardless of whether or
 * not execution ended because of a fatal runtime error or uncaught exception.
 * This means that in web applications your shutdown handlers will still be
 * called following user aborts in web applications (i.e. the Stop button).
 * 
 * Currently, Artax cannot invoke shutdown listeners when CLI process control 
 * actions interrupt script execution.
 * 
 * Note that you aren't required to specify any exception or shutdown listeners.
 * If you don't specify any listeners for these events, Artax will still act in 
 * a manner appropriate to the application-wide debug flag. When "debug" is turned
 * on, uncaught exceptions (and fatal runtime errors) will result in the standard
 * error traceback. When debugging is off, output will cease and execution will 
 * quietly terminate.
 * 
 * For more detailed information check out the relevant wiki page over on github:
 * https://github.com/rdlowrey/Artax/wiki/Error-Management
 * 
 * ### Crazy voodoo to allow extreme edge-case E_ERROR exception handling
 * 
 * Dear PHP: you're so awesome. In extreme fatal error scenarios the normal
 * stack of custom error, exception and shutdown function registrations
 * is insufficient to treat fatal errors like uncaught exceptions. The 
 * only known example (at the time of this writing) is a situation in which 
 * a normal E_NOTICE is tied to an E_ERROR by trying to call a method on
 * a non-existent variable:
 * 
 *     $varThatDoesntExist->imaginaryMethod();
 * 
 * The non-existent variable results in an E_NOTICE, which normally wouldn't
 * be a problem. The issue arises because calling a member function on a
 * non-object is a fatal E_ERROR. The custom error handler function can't
 * pull out of the E_NOTICE in time to handle the fatal error like an 
 * exception for graceful shutdown.
 * 
 * @category    Artax
 * @package     Core
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class Handler implements UnifiedHandler {

    /**
     * @var int
     */
    private $debugMode;
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * Helper flag for working around this bug:
     * https://bugs.php.net/bug.php?id=60909
     * 
     * @var bool
     */
    private $errChain = false;
    
    /**
     * Specifies debug mode and injects event mediator
     * 
     * @param int      $debugMode An app-wide debug run level
     * @param Mediator $mediator An instance of the event Mediator interface
     * 
     * @return void
     */
    public function __construct($debugMode, Mediator $mediator) {
    
        $this->debugMode = $debugMode;
        $this->mediator = $mediator;
    }
    
    /**
     * Notifies event listeners when PHP errors are raised
     * 
     * @param int    $errNo   The PHP error constant
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * 
     * @return void
     * @notifies error(ErrorException, bool)
     */
    public function error($errNo, $errStr, $errFile, $errLine) {
    
        $msg = "$errStr in $errFile on line $errLine";
        $e = new ErrorException($msg, $errNo);
        
        try {
            $listenerInvocationCount = $this->mediator->notify('error', $e, $this->debugMode);
            if (!$listenerInvocationCount && $this->debugMode) {
                echo $msg;
            }
        } catch (Exception $e) {
            
            // When fatal errors occur in the presence of output buffering PHP will
            // immediately flush the buffer. To avoid this output and allow our
            // handlers to process fatals correctly we prevent this flush by
            // clearing the buffer when a fatal error occurs.
            if (ob_get_contents()) {
                ob_end_clean();
            }
            
            // Because of the bug listed at  https://bugs.php.net/bug.php?id=60909
            // we manually call our exception/termination routine when error handling
            // results in an exception
            $this->errChain = true;
            $this->exception($e);
            $this->shutdown();
            throw new ScriptHaltException;
        }
    }
    
    /**
     * The "last chance" handler for uncaught exceptions 
     * 
     * All uncaught exceptions result in a system `exception` event. If no
     * listeners have been attached for this event the default handler message
     * is output according to the application-wide debug flag. It's important 
     * that any events listening for uncaught exceptions do not allow any
     * exceptions of their own to bubble up the stack, otherwise the default 
     * message will be displayed.
     * 
     * @param Exception $e Exception object
     *
     * @return void
     * @notifies exception(Exception $e, bool $debugMode)
     */
    public function exception(Exception $e) {
        
        if ($this->errChain && !($e instanceof FatalErrorException) && ob_get_contents()) {
            ob_clean();
        }
        
        if ($e instanceof ScriptHaltException) {
            return;
        } elseif ($e instanceof FatalErrorException) {
            if ($this->debugMode === 1) {
                ini_set('display_errors', true);
                error_reporting(E_ALL | E_STRICT);
            }
            try {
                if (!$this->mediator->notify('exception', $e, $this->debugMode)
                    && $this->debugMode
                ) {
                    echo $this->defaultHandlerMsg($e);
                }
                $this->mediator->notify('shutdown');
            } catch (ScriptHaltException $e) {
                return;
            } catch (Exception $e) {
                echo $this->defaultHandlerMsg($e);
            }
        } elseif ($this->mediator->count('exception')) {
            try {
                ob_start();
                $this->mediator->notify('exception', $e, $this->debugMode);
                ob_end_flush();
            } catch (ScriptHaltException $e) {
                ob_end_flush();
                return;
            } catch (Exception $e) {
                echo $this->defaultHandlerMsg($e);
                ob_end_flush();
            }
        } else {
            echo $this->defaultHandlerMsg($e);
        }
    }

    /**
     * Handle unexpected fatal errors and/or notify listeners of shutdown
     * 
     * @return void
     * @notifies shutdown()
     */
    public function shutdown() {
        
        // Due to the bug listed at https://bugs.php.net/bug.php?id=60909 this function's
        // behavior has already been handled if the `errChain` flag is truthy
        if ($this->errChain) {
            return;
        }
        
        if ($e = $this->getFatalErrorException()) {
            $this->exception($e);
        } else {
            try {
                $this->mediator->notify('shutdown');
            } catch (ScriptHaltException $e) {
                return;
            } catch (Exception $e) {
                echo $this->defaultHandlerMsg($e);
            }
        }
    }
    
    /**
     * Register the custom exception and shutdown handlers
     * 
     * @return Handlers Returns current object instance
     */
    public function register() {
    
        set_error_handler(array($this, 'error'));
        set_exception_handler(array($this, 'exception'));
        register_shutdown_function(array($this, 'shutdown'));
        
        return $this;
    }

    /**
     * Determine if the last triggered PHP error was fatal
     * 
     * If the last occuring error during script execution was fatal the function
     * returns a `FatalErrorException` object representing the error so it can be
     * handled by `Handlers::exception`.
     * 
     * @return FatalErrorException Returns null if the most recent PHP error wasn't fatal
     */
    public function getFatalErrorException() {
        
        $ex  = null;
        $err = $this->lastError();
        
        $fatals = array(
            E_ERROR           => 'Fatal Error',
            E_PARSE           => 'Parse Error',
            E_CORE_ERROR      => 'Core Error',
            E_CORE_WARNING    => 'Core Warning',
            E_COMPILE_ERROR   => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning'
        );
        
        if (isset($fatals[$err['type']])) {
            $msg = $fatals[$err['type']] . ': ' . $err['message'] . ' in ';
            $msg.= $err['file'] . ' on line ' . $err['line'];
            $ex  = new FatalErrorException($msg);
        }
        
        return $ex;
    }
    
    /**
     * Get an array representation of the most recently raised PHP error
     * 
     * This method exists primarily for testing purposes to allow mocking
     * of its behavior.
     * 
     * @return array Returns an associative error representation array
     */
    protected function lastError() {
    
        return error_get_last();
    }
    
    /**
     * Default exception message if no custom handling controller specified
     * 
     * @param Exception $e An uncaught exception object
     * 
     * @return string Returns a debug message if appropriate or null if the 
     *                application debug flag is turned off.
     */
    protected function defaultHandlerMsg(Exception $e) {
    
        return $this->debugMode ? (string) $e : null;
    }
}
