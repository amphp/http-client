<?php

/**
 * Artax Bootstrap File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

/*
 * --------------------------------------------------------------------
 * CHECK CONSTANTS & DEFINE ARTAX_SYSTEM_DIR
 * --------------------------------------------------------------------
 */

// Require PHP 5.4+
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
    die('Artax requires PHP 5.4 or higher' . PHP_EOL);
}

// Specify debug flag if it doesn't exist yet
if (!defined('AX_DEBUG_FLAG')) {
    define('AX_DEBUG_FLAG', TRUE);
}

// By convention Artax lib paths are resolved with a leading slash relative to 
// directory constants. Meanwhile, the `__DIR__` magic constant will return `/`
// if the directory is root. We avoid problems when using the root directory by
// setting `ARTAX_DIR` to an empty string if it's equal to the root directory.
define('AX_SYSTEM_DIR', __DIR__ === '/' ? '' : __DIR__);


/*
 * --------------------------------------------------------------------
 * EASE BOOT DEBUGGING: ERROR REPORTING SETTINGS CHANGED IN A BIT
 * --------------------------------------------------------------------
 */

ini_set('display_errors', TRUE);
error_reporting(E_ALL);
ini_set('html_errors', FALSE);


/*
 * --------------------------------------------------------------------
 * REGISTER AUTOLOADER FOR NON-ESSENTIAL ARTAX LIBS
 * --------------------------------------------------------------------
 */

require AX_SYSTEM_DIR . '/src/Artax/ClassLoaderInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/ClassLoaderAbstract.php';
require AX_SYSTEM_DIR . '/src/Artax/ClassLoader.php';

(new Artax\ClassLoader('Artax', AX_SYSTEM_DIR . '/src'))->register();


/*
 * --------------------------------------------------------------------
 * LOAD REQUIRED ARTAX LIBS
 * --------------------------------------------------------------------
 */

require AX_SYSTEM_DIR . '/src/Artax/Events/NotifierInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Events/NotifierTrait.php';
require AX_SYSTEM_DIR . '/src/Artax/Events/MediatorInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Events/Mediator.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/ErrorsInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/Errors.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/TerminationInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/Termination.php';
require AX_SYSTEM_DIR . '/src/Artax/Bootstrapper.php';


/*
 * --------------------------------------------------------------------
 * REGISTER CUSTOM ERROR HANDLER
 * --------------------------------------------------------------------
 */
 
(new Artax\Handlers\Errors(AX_DEBUG_FLAG))->register();


/*
 * --------------------------------------------------------------------
 * BOOT THE EVENT MEDIATOR AND ADD IT TO THE GLOBAL NAMESPACE
 * --------------------------------------------------------------------
 */

$artax = (new Artax\Bootstrapper(
    (new Artax\Handlers\Termination(AX_DEBUG_FLAG))->register(),
    new Artax\Events\Mediator
))->boot();
