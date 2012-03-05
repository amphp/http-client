#!/usr/bin/php
<?php

/**
 * Example CLI application 2
 * 
 * This example demonstrates how the artax exception handler treats uncaught
 * exceptions as events that can be mediated. Like example1.php, we store
 * our event listeners in the configuration (config2.php) file.
 * 
 * We've registered an event listener to fire on the "ax.boot_complete" event
 * that does nothing but throw a new exception. When this happens, the
 * "ax.uncaught_exception" event, for which we've specified a listener, fires
 * to handle the exception.
 * 
 * Also note that, like exceptions, shutdown is an event that can be mediated
 * and handled by multiple listeners with the normal mediator behavior. In this
 * case, the only event we've registered for shutdown execution simply outputs
 * a message to inform us that it was invoked.
 * 
 * Obviously, you'll need to specify the correct filepath to the artax.php
 * bootstrap below to get the program running as well as modify the hashbang as
 * needed (or execute the script directly using the php binary).
 * 
 * @category artax
 * @package  examples
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

define('AX_APP_PATH', dirname(__DIR__));
define('AX_CONFIG_FILE', AX_APP_PATH . '/conf/config2.php');
require '/mnt/data/dev/php/artax/artax.php';
