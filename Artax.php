<?php

/**
 * Artax Bootstrap File
 * 
 * PHP version 5.4
 * 
 * ### QUICK START
 * 
 * You need to do one thing in order to fire up an Artax application: require
 * the **Artax.php** bootstrap as follows ...
 * 
 *     require '/hard/path/to/Artax.php';
 * 
 * That's it. From there it's a simple matter of pushing event listeners onto
 * the event mediator (`$mediator`) and (optionally) adding dependency definitions
 * (if necessary) to the dependency injection container (`$provider`).
 * 
 * The only configuration directive to specify is an optional `AX_DEBUG` constant.
 * If not specified, application-wide debug output will be turned off. This
 * setting is appropriate for production environments. However, development
 * environments should turn debug mode on **before** including the bootstrap
 * file like so:
 * 
 *     define('AX_DEBUG', TRUE);
 *     require '/hard/path/to/Artax.php';
 * 
 * Examples to get you started are available in the {%ARTAX_DIR%}/examples
 * directory.
 * 
 * For more detailed discussion checkout the [github wiki][wiki]
 * 
 * [wiki]: https://github.com/rdlowrey/Artax/wiki
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

use Artax\Core\Provider,
    Artax\Core\Mediator,
    Artax\Core\Handlers;

/*
 * --------------------------------------------------------------------
 * CHECK FOR 5.4+ & DEFINE AX_DEBUG/AX_SYSDIR CONSTANTS
 * --------------------------------------------------------------------
 */

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
    die('Artax requires PHP 5.4 or higher' . PHP_EOL);
}

if (!defined('AX_DEBUG')) {
    define('AX_DEBUG', FALSE);
}

define('AX_SYSDIR', __DIR__);

/*
 * --------------------------------------------------------------------
 * SET ERROR REPORTING LEVELS
 * --------------------------------------------------------------------
 * 
 * The built-in Artax error handler turns all PHP errors into
 * `ErrorException` objects which are passed to listeners assigned to the 
 * system `error` event. Handling error events is up to you. If no `error`
 * listeners are attached, PHP errors will simply be ignored. This is not
 * recommended, obviously, and you should specify an event listener to
 * handle PHP errors. The normal behavior is for error listeners to simply
 * throw the `ErrorException` object. `error` listeners are also passed a
 * DEBUG flag so that handler behavior can be modified according to the 
 * application environment (dev/production).
 * 
 * ### Why is E_ERROR ignored?
 * 
 * It may seem counter-intuitive to disable reporting of `E_ERROR` output
 * at any time. However, a fatal error is always fatal, regardless of
 * whether it is reported or not. You can't actually suppress it. Just because
 * a fatal E_ERROR *shouldn't* occur in production code doesn't mean it won't.
 * Consider for example, a fatal "out of memory" error. In such cases we 
 * still need to prevent ugly error messages from being shown to end users.
 * Setting `display_errors = Off` will not prevent raw error output in the 
 * event of an `E_ERROR`. Instead, we must also use the `error_reporting` 
 * directive to prevent such displays. Artax transforms the fatal error
 * into a `FatalErrorException` which can be handled like any other uncaught 
 * exception using listeners for the system `exception` event. This allows 
 * you to treat fatals like a run-of-the-mill uncaught exception in production 
 * and display a graceful error message in conjunction with any necessary 
 * logging.
 */

ini_set('display_errors', FALSE);
ini_set('html_errors', FALSE);
error_reporting(E_ALL & ~ E_ERROR);

/*
 * --------------------------------------------------------------------
 * LOAD REQUIRED ARTAX LIBS
 * --------------------------------------------------------------------
 */

require AX_SYSDIR . '/src/Artax/Core/ProviderDefinitionException.php';
require AX_SYSDIR . '/src/Artax/Core/ProviderInterface.php';
require AX_SYSDIR . '/src/Artax/Core/Provider.php';
require AX_SYSDIR . '/src/Artax/Core/MediatorInterface.php';
require AX_SYSDIR . '/src/Artax/Core/Mediator.php';
require AX_SYSDIR . '/src/Artax/Core/FatalErrorException.php';
require AX_SYSDIR . '/src/Artax/Core/ScriptHaltException.php';
require AX_SYSDIR . '/src/Artax/Core/HandlersInterface.php';
require AX_SYSDIR . '/src/Artax/Core/Handlers.php';

/*
 * --------------------------------------------------------------------
 * BOOT THE EVENT MEDIATOR & DEPENDENCY PROVIDER
 * --------------------------------------------------------------------
 */

$provider = new Provider;
$mediator = new Mediator($provider);
$provider->share('Artax\\Core\\Mediator', $mediator);

/*
 * --------------------------------------------------------------------
 * REGISTER ERROR, EXCEPTION & SHUTDOWN HANDLERS
 * --------------------------------------------------------------------
 */

(new Handlers(AX_DEBUG, $mediator))->register();


