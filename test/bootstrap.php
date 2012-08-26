<?php

if (!defined('ARTAX_SYSTEM_DIR')) {
    define('ARTAX_SYSTEM_DIR', dirname(__DIR__));
}

/*
 * --------------------------------------------------------------------
 * Register Artax autoloader.
 * --------------------------------------------------------------------
 */

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        $class = str_replace('\\', '/', $class);        
        require ARTAX_SYSTEM_DIR . "/src/$class.php";
    }
});

/*
 * --------------------------------------------------------------------
 * Register PHP-Datastructures autoloader.
 * --------------------------------------------------------------------
 */

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Spl\\')) {
        $file = ARTAX_SYSTEM_DIR . "/vendor/PHP-Datastructures/src/";
        $file.= str_replace('\\', '/', $class) . '.php';
        require $file;
    }
});

/*
 * --------------------------------------------------------------------
 * Register vfsStream autoloader.
 * --------------------------------------------------------------------
 */

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'org\\bovigo\\vfs\\')) {
        $class = str_replace('\\', '/', $class);        
        require ARTAX_SYSTEM_DIR . "/vendor/vfsStream/src/main/php/$class.php";
    }
});
