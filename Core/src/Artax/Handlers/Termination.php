<?php

/**
 * Artax Termination Class File
 *
 * PHP version 5.4
 *
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace Artax\Handlers;
use Artax\Events\MediatorInterface,
    Exception;

/**
 * Termination Event Hander Class
 *
 * The Termination handler uses the event Mediator to enable unified, evented
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
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class Termination implements TerminationInterface
{
    /**
     * Flag specifying if full debug output should be shown when problems arise
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
     * Register the custom exception and shutdown handlers
     * 
     * @return Termination Returns object instance for method chaining.
     */
    public function register()
    {
        set_exception_handler([$this, 'exception']);
        register_shutdown_function([$this, 'shutdown']);
        return $this;
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
     * @param Exception $e Exception object
     *
     * @return void
     * @uses Termination::setException
     * @notifies exception(\Exception $e, bool $debug)
     */
    public function exception(\Exception $e)
    {
        if ($e instanceof ScriptHaltException) {
            return;
        } elseif ($e instanceof FatalErrorException) {
            try {
                $this->mediator->notify('exception', $e, $this->debug);
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
     * Handle unexpected fatal errors and/or notify listeners of shutdown
     * 
     * If script shutdown was caused by a fatal PHP error, the error is used to 
     * generate a corresponding `FatalErrorException` object which is then passed
     * to `Termination::exception` for handling.
     * 
     * The mediator is notified on shutdown so that any interested
     * listeners can act appropriately. If an event listener invoked by the
     * system `shutdown` event throws an uncaught exception it will not be handled
     * and script execution will cease immediately without sending further output.
     * 
     * @return void
     * @uses Termination::getFatalErrorException
     * @uses Termination::exception
     * @notifies shutdown()
     */
    public function shutdown()
    {
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
     * handled by `Termination::exception`.
     * 
     * @return mixed Returns NULL if no error occurred or a non-fatal error was 
     *               raised. An ErrorException is returned if the last error
     *               raised was fatal.
     * @used-by Termination::shutdown
     */
    public function getFatalErrorException()
    {
        $ex  = NULL;
        $err = $this->lastError();
        
        $fatals = [
            E_ERROR           => 'Fatal Error',
            E_PARSE           => 'Parse Error',
            E_CORE_ERROR      => 'Core Error',
            E_CORE_WARNING    => 'Core Warning',
            E_COMPILE_ERROR   => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning'
        ];
      
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
     * @used-by Termination::getFatalErrorException
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
    protected function defaultHandlerMsg(\Exception $e)
    {
        return $this->debug ? (string) $e : NULL;
    }
}
