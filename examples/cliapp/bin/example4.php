#!/usr/bin/php
<?php

/**
 * Example CLI application 4
 * 
 * This example demonstrates how a class can be instantiated and invoked 
 * manually or by using the dependency provider.
 * 
 * Obviously, you'll need to specify the correct filepath to the Artax.php
 * bootstrap below to get the program running as well as modify the hashbang as
 * needed (or execute the script directly using the php binary).
 * 
 * @category Artax
 * @package  examples
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

define('AX_APP_PATH', dirname(__DIR__));
define('AX_CONFIG_FILE', AX_APP_PATH . '/conf/config3.php');
require '/mnt/data/dev/php/artax/Artax.php';


// Let's add an event listener just for fun
$artax->mediator->push('intermission', function() {
  echo PHP_EOL . '... controller intermission ...' . PHP_EOL;
});


// Manually instantiate our test controller injecting the necessary dependencies
$cntrl = new controllers\Test($artax->mediator, new \Artax\Controllers\Response);
$cntrl->exec()->getResponse()->output();


// Let the mediator know we're taking a quick break between controllers
$cntrl->notify('intermission');


// Now lets do the same thing using the dependency provider ...
$cntrl = $artax->depProvider->make('controllers.Test');
$cntrl->exec()->getResponse()->output();



