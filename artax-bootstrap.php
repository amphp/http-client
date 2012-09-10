<?php

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
    throw new RuntimeException(
        'Artax requires PHP 5.3 or higher'
    );
}

if (extension_loaded('mbstring')) { 
    if (ini_get('mbstring.func_overload') & 2) { 
        throw new RuntimeException(
            'Artax cannot function in the presence of string function overloading ' .
            'with "mbstring.func_overload"'
        );
    }
}

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        require __DIR__ . "/src/$class.php";
    }
});

require __DIR__ . '/vendor/PHP-Datastructures/bootstrap.php';