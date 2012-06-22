<?php

/**
 * Artax Bootstrap File
 * 
 * PHP version 5.3
 * 
 * ### Quick Start
 * 
 * You need to do two things to fire up an Artax application:
 * 
 * 1. Specify the application-wide debug level;
 * 2. Require the the **Artax.php** bootstrap file.
 * 
 *     define('ARTAX_DEBUG_LEVEL', 1); // acceptable values: 0, 1, 2
 *     require '/hard/path/to/Artax.php';
 * 
 * That's it. From there it's a simple matter of pushing event listeners onto
 * the event mediator (`$mediator`) and (optionally) adding dependency definitions
 * (if necessary) to the dependency injection container (`$provider`).
 * 
 * ### More information
 * 
 * Examples to get you started are available in the {%ARTAX_DIR%}/examples
 * directory. For more detailed discussion checkout the wiki for extended
 * discussion and examples:
 * 
 * https://github.com/rdlowrey/Artax/wiki
 * 
 * ### Concerning ARTAX_DEBUG_LEVEL levels
 * 
 * Artax applications have three different debug output levels:
 * 
 *     - `define('ARTAX_DEBUG_LEVEL', 0); // production`
 *     - `define('ARTAX_DEBUG_LEVEL', 1); // development`
 *     - `define('ARTAX_DEBUG_LEVEL', 2); // debug nested fatal errors in development`
 * 
 * Production apps should always run in debug level 0. Level 1 results in
 * formatted output that correctly represents exceptions and fatal errors in
 * all but the most extreme cases. Finally, debug level 2 is necessary *only*
 * when debugging a fatal E_ERROR that occurs *inside an exception handler
 * that is already handling a fatal error*. An example of such a situation
 * would be an E_PARSE error in a class used by your exception event listener.
 * 
 * Artax goes to great lengths to turn fatal E_ERRORs into exceptions so
 * that applications can handle these situations like any other uncaught
 * exception. However, there's nothing to be done if your exception handler
 * triggers a fatal E_ERROR while already handling a fatal error. If you 
 * can't figure out why your app keeps breaking, try switching into debug 
 * level 2, as this will give your more information about the problem.
 * 
 * @category  Artax
 * @package   Core
 * @author    Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright ${copyright.msg}
 * @license   All code subject to the ${license.name}
 * @version   ${project.version}
 */

/*
 * --------------------------------------------------------------------
 * CHECK FOR 5.3+ & DEFINE ARTAX_DEBUG_LEVEL/ARTAX_SYSTEM_DIR CONSTANTS
 * --------------------------------------------------------------------
 */

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
    die('Artax requires PHP 5.3 or higher' . PHP_EOL);
}

define('ARTAX_SYSTEM_DIR', __DIR__);

/*
 * --------------------------------------------------------------------
 * SET ERROR REPORTING LEVELS
 * --------------------------------------------------------------------
 * 
 * The built-in Artax error handler turns all PHP errors into
 * `ErrorException` objects which are passed to listeners assigned to the 
 * system `error` event. Handling error events is up to you. If no `error`
 * listeners are attached, non-fatal PHP errors will simply be ignored. This
 * is not recommended, obviously, and you should specify an event listener to
 * handle PHP errors. 
 * 
 * Your error listener(s) might simply throw the `ErrorException` object to
 * treat all PHP errors as exceptions. `error` listeners are also passed the
 * application-wide debug level as a parameter. This allows your handlers
 * to behave differently in development and production environments.
 * 
 * ### Why is E_ERROR ignored?
 * 
 * It may seem counter-intuitive to disable reporting of `E_ERROR` output
 * at any time. However, a fatal error is always fatal, regardless of
 * whether it is reported or not. You can't actually suppress it. Just because
 * a fatal E_ERROR *shouldn't* occur in production code doesn't mean it won't.
 * Ignoring "impossible" conditions is how space shuttles blow up.
 * 
 * Consider for example, a fatal "out of memory" error. In such cases we 
 * still need to prevent ugly error messages from being shown to end users.
 * Setting `display_errors = Off` will not prevent raw error output in the 
 * case of a memory error. Instead, we must also use the `error_reporting` 
 * directive to prevent its display. Artax transforms the fatal error into
 * a `FatalErrorException` which can be handled like any other uncaught 
 * exception using listeners attached to the system `exception` event. This
 * allows applications to treat fatals as if they are run-of-the-mill uncaught 
 * exceptions and terminate gracefully (and perform necessary logging) when
 * unexpected fatals occur.
 */

if (!defined('ARTAX_DEBUG_LEVEL')) {
    define('ARTAX_DEBUG_LEVEL', 0);
}

if (ARTAX_DEBUG_LEVEL === 2) {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', TRUE);
} elseif (ARTAX_DEBUG_LEVEL === 1) {
    error_reporting((E_ALL | E_STRICT) & ~ E_ERROR);
    ini_set('display_errors', FALSE);
} elseif (ARTAX_DEBUG_LEVEL === 0) {
    error_reporting((E_ALL | E_STRICT) & ~ E_ERROR);
    ini_set('display_errors', FALSE);
} else {
    throw new DomainException(
        'Invalid ARTAX_DEBUG_LEVEL: '. ARTAX_DEBUG_LEVEL .'; 0, 1 or 2 expected.'
    );
}

ini_set('html_errors', FALSE);

/*
 * --------------------------------------------------------------------
 * LOAD REQUIRED ARTAX LIBS
 * --------------------------------------------------------------------
 */

require ARTAX_SYSTEM_DIR . '/src/Artax/ProviderDefinitionException.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/FatalErrorException.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/ScriptHaltException.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/BadListenerException.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/ReflectionPool.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/ReflectionCacher.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/InjectionContainer.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/Provider.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/Mediator.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/Notifier.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/UnifiedHandler.php';
require ARTAX_SYSTEM_DIR . '/src/Artax/Handler.php';

/*
 * --------------------------------------------------------------------
 * BOOT THE EVENT MEDIATOR & DEPENDENCY PROVIDER
 * --------------------------------------------------------------------
 */

$reflCacher = new Artax\ReflectionCacher;
$provider   = new Artax\Provider($reflCacher);
$notifier   = new Artax\Notifier($provider);

$provider->share('Artax\\Notifier', $notifier);
$provider->share('Artax\\Provider', $provider);
$provider->share('Artax\\ReflectionCacher', $reflCacher);

/*
 * --------------------------------------------------------------------
 * REGISTER ERROR, EXCEPTION & SHUTDOWN HANDLERS
 * --------------------------------------------------------------------
 */

if (PHP_VERSION_ID >= 50400) {
    (new Artax\Handler(ARTAX_DEBUG_LEVEL, $notifier))->register();
} else {
    $handlers = new Artax\Handler(ARTAX_DEBUG_LEVEL, $notifier);
    $handlers->register();
    unset($handlers);
}

