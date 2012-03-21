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
use ErrorException;

/**
 * ErrorHandler Class
 * 
 * Defines a custom error handling function to throw an `ErrorException` on any
 * raised PHP error (except fatals, of course). Fatal errors cannot be handled
 * by custom error handlers and are dealt with at the shutdown level by the
 * `Termination` handler class.
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
     * Initialize error reporting settings and register the error handler
     * 
     * @param bool $debug Determines system-wide error reporting levels
     * 
     * @return void
     */
    public function __construct($debug = TRUE)
    {
        $this->debug = (bool) $debug;
    }
    
    /**
     * Register the custom error handler and set error reporting levels
     * 
     * It may seem counter-intuitive to disable reporting of `E_ERROR` output
     * at any time. However, a fatal error is always fatal, regardless of
     * whether it is reported or not. You can't actually suppress it.
     * 
     * The php.ini `display_errors = Off` is not sufficient to prevent raw output
     * in the event of a fatal `E_ERROR`. Instead, we must also use `error_reporting`
     * to prevent such displays as specified in the code below. It's important 
     * to note that when employing this method there should also be an appropriate
     * shutdown function registered to collect information regarding the `E_ERROR`
     * that was raised. Otherwise the script will simply terminate and you'll 
     * have no inkling as to why. The built-in `Artax\Core\Handlers\Termination` 
     * class accomplishes this for you.
     * 
     * Obviously, in production environments it's always prudent to hide any 
     * potential fatal error  output from the end user. Just because it's 
     * unlikely that you'll get an `E_ERROR` in a production environment doesn't
     * mean we shouldn't account for this scenario.
     * 
     * Saying, "I don't need to handle a potential situation because it's a
     * very remote possibility," is how space shuttles blow up.
     * 
     * So, this documentation exists for the edification of any passersby and
     * to prevent someone from coming along and editing the arguments to the
     * `error_reporting` call in the method's `if` control structure. Don't
     * change it: it's there for a reason.
     * 
     * @return ErrorHandler Returns object instance for method chaining.
     */
    public function register()
    {
        if (!$this->debug) {
            error_reporting(E_ALL & ~ E_ERROR & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', FALSE);
        } else {
            // error_reporting(E_ALL) is set in the Artax.php bootstrap file
            ini_set('display_errors', TRUE);
        }
        set_error_handler([$this, 'handle']);
        return $this;
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
        throw new ErrorException($msg);
    }
}
