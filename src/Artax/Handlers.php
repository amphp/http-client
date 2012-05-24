<?php

/**
 * Artax Handlers Class File
 *
 * PHP version 5.3
 *
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    All code subject to the ${license.name}
 * @version    ${project.version}
 */
namespace Artax;
use Exception, ErrorException;

/**
 * Provides error, uncaught exception and shutdown handling
 *
 * The Handlers class uses the event Mediator to enable unified, evented
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
 * actions interrupt script execution. Stay tuned, though; support for this 
 * behavior is planned for upcoming releases.
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
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class Handlers implements HandlersInterface
{
    /**
     * The application-wide debug level
     * @var int
     */
    private $debug;
    
    /**
     * Helper flag for extraordinary fatal error conditions
     * @var bool
     */
    private $errChain = FALSE;
    
    /**
     * An event mediator instance
     * @var Mediator
     */
    private $mediator;
    
    /**
     * Specify debug output flag and register exception/shutdown handlers
     * 
     * @param int               $debug    An app-wide debug run level
     * @param MediatorInterface $mediator An event mediator instance
     * 
     * @return void
     */
    public function __construct($debug, MediatorInterface $mediator)
    {
        $this->debug    = $debug;
        $this->mediator = $mediator;
    }
    
    /**
     * Notify event listeners when PHP errors are raised
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
     * ### (1) IMPORTANT: Note on output buffering
     * 
     * When fatal errors occur in the presence of output buffering (ob_start)
     * PHP immediately flushes the buffer. To avoid this output and allow
     * our handlers to process fatals correctly we prevent this flush by
     * clearing the buffer when a fatal error occurs.
     * 
     * @param int    $errNo   The PHP error constant
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * 
     * @return void
     * @notifies error(ErrorException, bool)
     */
    public function error($errNo, $errStr, $errFile, $errLine)
    {
        $msg = "$errStr in $errFile on line $errLine";
        $e   = new ErrorException($msg, $errNo);
        
        try {
            $count = $this->mediator->notify('error', $e, $this->debug);
            if (!$count && $this->debug) {
                echo $msg;
            }
        } catch (Exception $e) {
            if (ob_get_contents()) {
                ob_end_clean(); // <-- IMPORTANT: see docblock (1) for info
            }
            $this->errChain = TRUE;
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
     * Note that the system `shutdown` event always fires regardless of whether
     * or not an `exception` event was triggered.
     * 
     * ### (1) IMPORTANT: Note on output buffering and fatal errors
     * 
     * When fatal errors occur in the presence of output buffering (ob_start)
     * PHP immediately flushes the buffer. To avoid this output and allow
     * our handlers to process fatals correctly we prevent this flush by
     * clearing the buffer when a fatal error occurs. The reason for the
     * fatal error is already catalogged in the generated exception message,
     * so any output is superfluous.
     * 
     * ### (2) IMPORTANT: Note on nested fatal errors
     * 
     * These handlers turn fatal errors into "uncaught exceptions" so that
     * exception event listeners can handle them like any other exception.
     * The only time this is a problem is if an exception event listener
     * causes a fatal E_ERROR *while* it's handling a fatal error.
     * 
     * Not surprisingly, there's just no way to handle a fatal error that
     * occurs while handling another fatal error. This can make for debugging
     * nightmares, but we don't want to enable error reporting for E_ERROR
     * because end-users could see the raw error output. Instead, when
     * handling fatals we turn fatal error output back on in debug mode,
     * allowing us to debug the source of the problem. If debug mode is
     * turned off, this situation results in an innocuous 500 response
     * with no output.
     * 
     * In summary: MAKE SURE YOUR CLASS EXCEPTION LISTENERS CAN BE INSTANTIATED
     * WITHOUT RAISING A FATAL E_ERROR.
     * 
     * If you're having problems debugging an error, switch your application
     * into debug mode 2, i.e. `define('AX_DEBUG', 2);` before including
     * the Artax bootstrap file. This will result in the most comprehensive
     * debugging output. Debug level 2 is only necessary in the most extreme 
     * error situations in which a fatal error is encountered inside the
     * handler for a previous fatal error.
     * 
     * @param Exception $e Exception object
     *
     * @return void
     * @uses Handlers::setException
     * @notifies exception(Exception $e, bool $debug)
     */
    public function exception(Exception $e)
    {
        if (!$this->errChain
            && !($e instanceof FatalErrorException)
            && ob_get_contents()
        ) {
            ob_clean(); // <-- IMPORTANT: see docblock (1) for info
        }
        
        if ($e instanceof ScriptHaltException) {
            return;
        } elseif ($e instanceof FatalErrorException) {
            if ($this->debug === 1) {
                ini_set('display_errors', TRUE); // <-- (2) docblock
                error_reporting(E_ALL); // <-- (2) docblock
            }
            try {
                if (!$this->mediator->notify('exception', $e, $this->debug)
                    && $this->debug
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
                $this->mediator->notify('exception', $e, $this->debug);
            } catch (ScriptHaltException $e) {
                return;
            } catch (Exception $e) {
                echo $this->defaultHandlerMsg($e);
            }
        } else {
            echo $this->defaultHandlerMsg($e);
        }
    }
    
    /**
     * Register the custom exception and shutdown handlers
     * 
     * @return Handlers Returns object instance
     */
    public function register()
    {
        set_error_handler(array($this, 'error'));
        set_exception_handler(array($this, 'exception'));
        register_shutdown_function(array($this, 'shutdown'));
        return $this;
    }

    /**
     * Handle unexpected fatal errors and/or notify listeners of shutdown
     * 
     * If script shutdown was caused by a fatal PHP error, the error is used to 
     * generate a corresponding `FatalErrorException` object which is then passed
     * to `Handlers::exception` for handling.
     * 
     * The mediator is notified on shutdown so that any interested
     * listeners can act appropriately. If an event listener invoked by the
     * system `shutdown` event throws an uncaught exception it will not be handled
     * and script execution will cease immediately without sending further output.
     * 
     * @return void
     * @uses Handlers::getFatalErrorException
     * @uses Handlers::exception
     * @notifies shutdown()
     */
    public function shutdown()
    {
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
     * Determine if the last triggered PHP error was fatal
     * 
     * If the last occuring error during script execution was fatal the function
     * returns an `ErrorException` object representing the error so it can be
     * handled by `Handlers::exception`.
     * 
     * @return mixed Returns NULL if no error occurred or a non-fatal error was 
     *               raised. An ErrorException is returned if the last error
     *               raised was fatal.
     * @used-by Handlers::shutdown
     */
    public function getFatalErrorException()
    {
        $ex  = NULL;
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
     * @used-by Handlers::getFatalErrorException
     */
    protected function lastError()
    {
        return error_get_last();
    }
    
    /**
     * Default exception message if no custom handling controller specified
     * 
     * @param Exception $e An uncaught exception object
     * 
     * @return string Returns a debug message if appropriate or NULL if the 
     *                application debug flag is turned off.
     */
    protected function defaultHandlerMsg(Exception $e)
    {
        return $this->debug ? (string) $e : NULL;
    }
}
