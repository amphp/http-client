#!/usr/bin/php
<?php

define('AX_DEBUG_FLAG', TRUE); // optional -- defaults to TRUE if not defined
//require '/path/to/artax/bootstrap/Artax.php';
require '/mnt/data/dev/php/artax/Artax.php';
// --- END FRAMEWORK SETUP




// Define a constant pointing to our app's directory to make life easier
define('MY_APP_PATH', dirname(__DIR__));

// Add some listeners
require MY_APP_PATH . '/src/listeners.php';
$artax->pushAll($listeners);

// Let's fire this baby up!
$artax->notify('ready');
