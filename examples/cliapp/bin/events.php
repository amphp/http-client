#!/usr/bin/php
<?php

define('AX_DEBUG', TRUE); // optional -- defaults to FALSE if not defined
require dirname(dirname(dirname(__DIR__))) . '/Artax.php'; // hard path to bootstrap
// --- END ARTAX SETUP




// Define a constant pointing to our app's directory to make life easier
define('MY_APP_PATH', dirname(__DIR__));

// Add some listeners
require MY_APP_PATH . '/src/listeners.php';
$artax->pushAll($listeners);

// Let's fire this baby up!
$artax->notify('ready');
