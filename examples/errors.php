#!/usr/bin/php
<?php

/*
 * --------------------------------------------------------------------
 * Specify debug mode; boot Artax.
 * --------------------------------------------------------------------
 */

define('AX_DEBUG', 1); // 0: production, 1: dev, 2: crazy nested fatals debugging
require dirname(__DIR__) . '/Artax.php'; // hard path to bootstrap file

/*
 * --------------------------------------------------------------------
 * Specify error, exception and shutdown event listeners.
 * --------------------------------------------------------------------
 */

$mediator->push('error', function(ErrorException $e, $debug) {
    
    echo  '---error event listener #1---' . PHP_EOL;
    
    $code = $e->getCode();
    
    if ($debug) {
        throw $e;
    }
    if ($code == E_STRICT || $code == E_DEPRECATED) {
        // do some logging or just ignore
    }
});

$mediator->pushAll(array(
    'exception' => array(
        function(Exception $e, $debug) {
            $debug = $debug ? 'TRUE' : 'FALSE';
            echo  '---exception event listener #1---' . PHP_EOL;
            echo "Debug setting: $debug" . PHP_EOL;
            echo 'Message: ' . $e->getMessage() . PHP_EOL;
        },
        function(Exception $e, $debug) {
            echo '---exception event listener #2---' . PHP_EOL;
        }
    ),
    'shutdown' => array(
        function() { 
            echo '---shutdown event listener #1---' . PHP_EOL;
        },
        function() { 
            echo '---shutdown event listener #2---' . PHP_EOL;
        }
    )
));

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
 * Finally, if you comment out all of the errors above you'll see that shutdown
 * handlers are called on their own after normal script termination.
 */

