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
 * If not defined, the `AX_CONFIG_FILE` constant will be set as so:
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



require AX_SYSTEM_DIR . '/src/artax/Bootstrapper.php';
require AX_SYSTEM_DIR . '/src/artax/BucketInterface.php';
require AX_SYSTEM_DIR . '/src/artax/BucketArrayAccessTrait.php';
require AX_SYSTEM_DIR . '/src/artax/Bucket.php';
require AX_SYSTEM_DIR . '/src/artax/BucketSettersTrait.php';
require AX_SYSTEM_DIR . '/src/artax/Config.php';
require AX_SYSTEM_DIR . '/src/artax/ConfigLoader.php';
require AX_SYSTEM_DIR . '/src/artax/UsesConfigTrait.php';
require AX_SYSTEM_DIR . '/src/artax/HandlersInterface.php';
require AX_SYSTEM_DIR . '/src/artax/Handlers.php';
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

// HTTP stuff
require AX_SYSTEM_DIR . '/src/artax/blocks/views/ViewInterface.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpMatcher.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpRouter.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpRequest.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/BucketInterface.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/BucketAbstract.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/ServerBucket.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HeaderBucket.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/ParamBucket.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/CookieBucket.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpControllerInterface.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpControllerAbstract.php';
require AX_SYSTEM_DIR . '/src/artax/ResponseInterface.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpResponseInterface.php';
require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpResponse.php';

require AX_SYSTEM_DIR . '/src/vendors/SplClassLoader.php';

/*
 * --------------------------------------------------------------------
 * BOOT WITHOUT CLUTTERING THE GLOBAL NAMESPACE
 * --------------------------------------------------------------------
 */

// Initialize Artax class autoloader
(new \SplClassLoader('artax', AX_SYSTEM_DIR.'/src'))->register();

// Instantiate Bootstrapper to load artax container
$ax = (new artax\Bootstrapper(
  new artax\ConfigLoader(AX_CONFIG_FILE),
  new artax\Config,
  new artax\Handlers,
  new artax\RouteList,
  new artax\Bucket,
  new artax\DepProvider(new artax\DotNotation)
))->initErrHandler()
  ->initConfig()
  ->initRoutes()
  ->initdeps()
  ->getBucket();
