#!/usr/bin/php
<?php

/**
 * Example CLI application 1
 * 
 * This example demonstrates storing all the event listeners in the configuration
 * file. The config file specifies a listener for the "app.ready" event
 * so the appropriate events are invoked with no additional code in this binary
 * file.
 * 
 * Notice how the final "app.questions" event listener doesn't fire. This is
 * expected because the penultimate listener for the specified event returns
 * `FALSE`, which ends invocation of listeners in the mediator's event queue.
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
define('AX_CONFIG_FILE', AX_APP_PATH . '/conf/config1.php');
require '/mnt/data/dev/php/artax/artax.php';
