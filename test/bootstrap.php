<?php

// Register Artax autoloader
spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        $class = str_replace('\\', '/', $class);        
        require dirname(__DIR__) . "/src/$class.php";
    }
});

// Register PHP-Datastructures autoloader
spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Spl\\')) {
        $file = dirname(__DIR__) . "/vendor/PHP-Datastructures/src/";
        $file.= str_replace('\\', '/', $class) . '.php';
        require $file;
    }
});