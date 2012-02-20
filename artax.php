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
 * This declaration must be made prior to including the *artax.php* bootstrap file.
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
 * `AX_CONFIG_FILE` constant prior to inclusion of the `artax.php` bootstrap file.
 * 
 * @category artax
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


// Exceptions that could be thrown before the autoloader is registered
require AX_SYSTEM_DIR . '/src/artax/exceptions/Exception.php';
require AX_SYSTEM_DIR . '/src/artax/exceptions/ErrorException.php';
require AX_SYSTEM_DIR . '/src/artax/exceptions/UnexpectedValueException.php';

// Core libs
require AX_SYSTEM_DIR . '/src/artax/Bootstrapper.php';
require AX_SYSTEM_DIR . '/src/artax/BucketInterface.php';
require AX_SYSTEM_DIR . '/src/artax/BucketArrayAccessTrait.php';
require AX_SYSTEM_DIR . '/src/artax/Bucket.php';
require AX_SYSTEM_DIR . '/src/artax/BucketSettersTrait.php';
require AX_SYSTEM_DIR . '/src/artax/Config.php';
require AX_SYSTEM_DIR . '/src/artax/ConfigLoader.php';
require AX_SYSTEM_DIR . '/src/artax/ControllerInterface.php';
require AX_SYSTEM_DIR . '/src/artax/ResponseControllerInterface.php';
require AX_SYSTEM_DIR . '/src/artax/ExControllerInterface.php';
require AX_SYSTEM_DIR . '/src/artax/ExControllerTrait.php';
require AX_SYSTEM_DIR . '/src/artax/HandlerInterface.php';
require AX_SYSTEM_DIR . '/src/artax/Handler.php';
require AX_SYSTEM_DIR . '/src/artax/RouteInterface.php';
require AX_SYSTEM_DIR . '/src/artax/Route.php';
require AX_SYSTEM_DIR . '/src/artax/RouteList.php';
require AX_SYSTEM_DIR . '/src/artax/MatcherInterface.php';
require AX_SYSTEM_DIR . '/src/artax/Matcher.php';
require AX_SYSTEM_DIR . '/src/artax/ProviderInterface.php';
require AX_SYSTEM_DIR . '/src/artax/DotNotation.php';
require AX_SYSTEM_DIR . '/src/artax/DepProvider.php';
require AX_SYSTEM_DIR . '/src/artax/RequestInterface.php';
require AX_SYSTEM_DIR . '/src/artax/RouterInterface.php';
require AX_SYSTEM_DIR . '/src/artax/RouterAbstract.php';
require AX_SYSTEM_DIR . '/src/artax/ResponseInterface.php';

// Class autoloader -- required last to avoid accidentally autoloading core libs
require AX_SYSTEM_DIR . '/src/artax/ClassLoader.php';


/*
 * --------------------------------------------------------------------
 * BOOT & GENERATE REQUEST/RESPONSE
 * --------------------------------------------------------------------
 */


// Instantiate the Bootstrapper class and generate a request/response
(new artax\Bootstrapper(
  new artax\ConfigLoader(AX_CONFIG_FILE),
  new artax\Config,
  new artax\DotNotation,
  new artax\RouteList,
  new artax\DepProvider(new artax\DotNotation)
))->initErrHandler()
  ->initConfig()
  ->loadBundles()
  ->loadAutoRequires()
  ->initClassAutoloaders()
  ->initDepProvider()
  ->initHandler()
  ->initRoutes()
  ->doRequest();
