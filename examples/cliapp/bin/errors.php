#!/usr/bin/php
<?php

define('AX_DEBUG_FLAG', FALSE); // optional -- defaults to TRUE if not defined
require '/path/to/artax/bootstrap/Artax.php';
// --- END FRAMEWORK SETUP




/**
 * Un-comment the throw line below to see what happens when no listeners are 
 * specified for the system "exception" event and an exception is thrown. Then
 * try cycling the AX_DEBUG_FLAG at the top to see how that affects the output
 * when no listeners are specified.
 */
//throw new \Exception('test exception message');

/**
 * Un-comment the code below to see what happens when no listeners are specified
 * for the system "shutdown" event and a fatal E_ERROR is raised. Then, try
 * cycling the AX_DEBUG_FLAG at the top to see how that affects the output.
 * 
 * This code will generate a fatal "Allowed memory size ..." E_ERROR.
 */
ini_set('memory_limit', '1M');
$data = '';
while(1) {
    $data .= str_repeat('#', PHP_INT_MAX);
}
