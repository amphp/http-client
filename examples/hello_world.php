#!/usr/bin/php
<?php

/*
 * --------------------------------------------------------------------
 * Specify debug mode; boot Artax.
 * --------------------------------------------------------------------
 */

define('AX_DEBUG', 1);
require dirname(__DIR__) . '/Artax.php'; // hard path to bootstrap file

/*
 * --------------------------------------------------------------------
 * Specify an event listener; broadcast that event.
 * --------------------------------------------------------------------
 */

$mediator->push('hello', function(){ echo "Hello, world." . PHP_EOL; });
$mediator->notify('hello');
