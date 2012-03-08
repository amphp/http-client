<?php

/**
 * Artax Bootstrap File
 * 
 * PHP version 5.4
 * 
 * ### GETTING STARTED
 * 
 * Before doing anything else you must inform Artax where your app lives by
 * specify the path to the application using the `ARTAX_APP_PATH` constant.
 * 
 * **IMPORTANT**: by convention Artax resolves file system paths with a leading 
 * slash relative to directory constants. This means that your `ARTAX_APP_PATH`
 * **should not** end with a trailing slash.
 * 
 * ```php
 * define('AX_APP_PATH', '/absolute/path/to/myapp');
 * ```
 * 
 * This declaration must be made prior to including the *Artax.php* bootstrap file.
 * The `ARTAX_APP_PATH` **does not** refer to the location of the Artax library 
 * files. Instead, it must point to the directory containing your application.
 * 
 * ### MULTIPLE CONFIGURATION ENVIRONMENTS
 * 
 * If not defined, the `AX_CONFIG_FILE` constant will default to the following:
 * 
 * ```php
 * if ( ! defined('AX_CONFIG_FILE')) {
 *   define('AX_CONFIG_FILE', AX_APP_PATH . '/conf/config.php');
 * }
 * ```
 * 
 * Users may specify a custom config file and define its location using the 
 * `AX_CONFIG_FILE` constant prior to inclusion of the `Artax.php` bootstrap file.
 * 
 * @category Artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */


/*
 * --------------------------------------------------------------------
 * CHECK CONSTANTS & DEFINE ARTAX_DIR
 * --------------------------------------------------------------------
 */


if ( ! defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
    die('Artax requires PHP 5.4 or higher' . PHP_EOL);
}

if ( ! defined('AX_APP_PATH')) {
    die('AX_APP_PATH constant must be specified prior to initialization' . PHP_EOL);
}

// By convention Artax lib paths are resolved with a leading slash relative to 
// directory constants. Meanwhile, the `__DIR__` magic constant will return `/`
// if the directory is root. We avoid problems when using the root directory by
// setting `ARTAX_DIR` to an empty string if it's equal to the root directory.
define('AX_SYSTEM_DIR', __DIR__ === '/' ? '' : __DIR__);

// Allow specification of a custom config file path. If not specified, the
// location defaults to AX_APP_PATH/conf/config.php
if ( ! defined('AX_CONFIG_FILE')) {
    define('AX_CONFIG_FILE', AX_APP_PATH . '/conf/config.php');
}


/*
 * --------------------------------------------------------------------
 * EASE BOOT DEBUGGING: ERROR REPORTING SETTINGS CHANGED AT CONFIG TIME
 * --------------------------------------------------------------------
 */


ini_set('display_errors', TRUE);
error_reporting(E_ALL);
ini_set('html_errors', FALSE);


/*
 * --------------------------------------------------------------------
 * LOAD REQUIRED LIBS
 * --------------------------------------------------------------------
 */

// Core libs

require AX_SYSTEM_DIR . '/src/Artax/Events/NotifierInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Events/NotifierTrait.php';
require AX_SYSTEM_DIR . '/src/Artax/Events/MediatorInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Events/Mediator.php';

require AX_SYSTEM_DIR . '/src/Artax/Controllers/ControllerInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Controllers/ResponseControllerInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Controllers/ResponseInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Controllers/Response.php';

require AX_SYSTEM_DIR . '/src/Artax/Handlers/ErrorHandlerInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/ErrorHandler.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/FatalHandlerInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Handlers/FatalHandler.php';

require AX_SYSTEM_DIR . '/src/Artax/App.php';
require AX_SYSTEM_DIR . '/src/Artax/BucketInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/BucketArrayAccessTrait.php';
require AX_SYSTEM_DIR . '/src/Artax/Bucket.php';
require AX_SYSTEM_DIR . '/src/Artax/BucketSettersTrait.php';
require AX_SYSTEM_DIR . '/src/Artax/Config.php';
require AX_SYSTEM_DIR . '/src/Artax/ConfigLoader.php';

require AX_SYSTEM_DIR . '/src/Artax/Routing/RequestInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Routing/RouteInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Routing/Route.php';
require AX_SYSTEM_DIR . '/src/Artax/Routing/RouteList.php';
require AX_SYSTEM_DIR . '/src/Artax/Routing/MatcherInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/Routing/Matcher.php';

require AX_SYSTEM_DIR . '/src/Artax/ProviderInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/DotNotation.php';
require AX_SYSTEM_DIR . '/src/Artax/DepProvider.php';

require AX_SYSTEM_DIR . '/src/Artax/ClassLoaderInterface.php';
require AX_SYSTEM_DIR . '/src/Artax/ClassLoaderAbstract.php';
require AX_SYSTEM_DIR . '/src/Artax/ClassLoader.php';
require AX_SYSTEM_DIR . '/src/Artax/ClassLoaderFactory.php';


/*
 * --------------------------------------------------------------------
 * BOOT & GENERATE REQUEST/RESPONSE
 * --------------------------------------------------------------------
 */
 

$artax = new Artax\App(
    (new Artax\ConfigLoader)->setConfigFile(AX_CONFIG_FILE),
    new Artax\Config,
    new Artax\Handlers\ErrorHandler,
    new Artax\Handlers\FatalHandler,
    new Artax\ClassLoaderFactory,
    new Artax\DepProvider(new Artax\DotNotation),
    new Artax\Events\Mediator
);
$artax->boot();
$artax->notify('app.ready');
