#!/usr/bin/php
<?php

/**
 * Example CLI application 3
 * 
 * This example demonstrates how events can be specified dynamically after the
 * Artax boot process completes.
 * 
 * Because the `$artax` variable is initialized at boot time, we can access it
 * in the global scope to perform any necessary tasks for our app after the
 * boot process is complete.
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




$artax->mediator->push('my_event_name_is_not_important', function() {
  $this->notify('namespace.event_chain');
});

$moreListeners = [
  ['namespace.event_chain', function() { /* Don't do anything */ }],
  ['namespace.event_chain', function() {
    $this->notify('last_event');
  }],
  ['last_event', function() {
    $controller = $this->depProvider->make('controllers.Test');
    $controller->exec()->getResponse()->output();
  }]
];

foreach ($moreListeners as $listener) {
  $artax->mediator->push($listener[0], $listener[1]);
}

$artax->notify('my_event_name_is_not_important'); // start the event chain



