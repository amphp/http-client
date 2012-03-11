#!/usr/bin/php
<?php

define('AX_DEBUG', TRUE); // optional -- defaults to FALSE if not defined
require dirname(dirname(dirname(__DIR__))) . '/Artax.php'; // hard path to bootstrap
// --- END ARTAX SETUP

/*
$artax->pushAll([
    'exception' => [
        function(Exception $e, $debug) {
            $debug = $debug ? 'TRUE' : FALSE;
            echo  '---exception listener #1---' . PHP_EOL;
            echo "Debug setting: $debug" . PHP_EOL;
            echo 'Message: ' . $e->getMessage() . PHP_EOL;
        },
        function(Exception $e, $debug) {
            echo '---exception listener #2---' . PHP_EOL;
        }
    ],
    'shutdown' => [
        function() { 
            echo '---shutdown listener #1---' . PHP_EOL;
        },
        function() { 
            echo '---shutdown listener #2---' . PHP_EOL;
        }
    ]
]);
*/


/**
 * Un-comment the throw line below to see what happens when no listeners are 
 * specified for the system "exception" event and an exception is thrown. Then
 * try cycling the AX_DEBUG flag at the top to see how that affects the output
 * when no listeners are specified. Also try uncommenting the above listener
 * attachment to see how listeners are invoked for an uncaught exception.
 */
throw new \Exception('test exception message');

/**
 * Comment out the above throw to see what happens when no listeners are specified
 * for the system "shutdown" event and a fatal E_ERROR is raised. Then, try
 * cycling the AX_DEBUG flag as well as uncommenting the listener attachment.
 * 
 * This code will generate a fatal "Allowed memory size ..." E_ERROR.
 */
ini_set('memory_limit', '1M');
$data = '';
while(1) {
    $data .= str_repeat('#', PHP_INT_MAX);
}
