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
/*
if (extension_loaded('openssl')) { 
    throw new RuntimeException(
        'Artax cannot function in the presence of string function overloading ' .
        'with "mbstring.func_overload"'
    );
}
*/
spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        require __DIR__ . "/src/$class.php";
    }
});

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Spl\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/vendor/PHP-Datastructures/src/$class.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});