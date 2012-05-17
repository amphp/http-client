#!/usr/bin/php
<?php

/*
 * --------------------------------------------------------------------
 * BOOT UP THE ARTAX-CORE
 * --------------------------------------------------------------------
 */

define('AX_DEBUG', TRUE); // optional -- defaults to FALSE if not defined
require dirname(__DIR__) . '/Artax.php'; // hard path to bootstrap file


/*
 * --------------------------------------------------------------------
 * SPECIFY LISTENERS FOR ERRORS, EXCEPTIONS & SHUTDOWNS
 * --------------------------------------------------------------------
 */

$mediator->pushAll([
    'error' => [
        function(ErrorException $e, $debug) {
            echo  '---error event listener #1---' . PHP_EOL;
            if (!$debug && in_array($e->getCode(), [E_STRICT, E_DEPRECATED])) {
                // log the message in production environments
            } else {
                throw $e;
            }
        }  
    ],
    'exception' => [
        function(Exception $e, $debug) {
            $debug = $debug ? 'TRUE' : 'FALSE';
            echo  '---exception event listener #1---' . PHP_EOL;
            echo "Debug setting: $debug" . PHP_EOL;
            echo 'Message: ' . $e->getMessage() . PHP_EOL;
        },
        function(Exception $e, $debug) {
            echo '---exception event listener #2---' . PHP_EOL;
        }
    ],
    'shutdown' => [
        function() { 
            echo '---shutdown event listener #1---' . PHP_EOL;
        },
        function() { 
            echo '---shutdown event listener #2---' . PHP_EOL;
        }
    ]
]);


/**
 * Notice how a PHP error results in error event listeners being invoked.
 * The specified error listener will throw an exception, which in turn
 * results in the uncaught exception listeners being invoked and subsequently
 * the shutdown listeners. If our error event listener didn't throw an
 * exception, though, execution would continue like the error didn't happen.
 */
trigger_error('Your mother was a hamster', E_USER_ERROR);


/**
 * Un-comment the throw line below to see what happens when no listeners are 
 * specified for the system "exception" event and an exception is thrown. Then
 * try cycling the AX_DEBUG flag at the top to see how that affects the output
 * when no listeners are specified. Also try uncommenting the above listener
 * attachment to see how listeners are invoked for an uncaught exception.
 */
throw new Exception('test exception message');

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

/**
 * Finally, you can comment out both error causing sections to see how the 
 * shutdown handlers are called on their own after normal script termination.
 */

